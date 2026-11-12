<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Cli;

use const SIGHUP;
use const SIGINT;
use const SIGTERM;

use Common\Runtime\RuntimeResetterInterface;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Psr\Log\LoggerInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function function_exists;
use function gethostname;
use function getmypid;
use function memory_get_peak_usage;
use function pcntl_async_signals;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function sleep;
use function sprintf;
use function strtolower;
use function substr;
use function trim;

final class WorkerRunCommand extends Command
{
    private const int DEFAULT_TIMEOUT         = 30;
    private const int DEFAULT_MAX_JOBS        = 0; // 0 means unlimited
    private const string DEFAULT_MEMORY_LIMIT = '128M';

    private bool $shouldShutdown = false;

    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobProcessor $jobProcessor,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RuntimeResetterInterface $runtimeResetter = null,
        private readonly ?SentryLifecycle $sentryLifecycle = null,
        private readonly ?SentryReporter $sentryReporter = null,
    ) {
        parent::__construct('worker:run');
        $this->setDescription('Run background job worker');
    }

    protected function configure(): void
    {
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_OPTIONAL,
            'Polling timeout in seconds',
            (string) self::DEFAULT_TIMEOUT,
        );

        $this->addOption(
            'memory-limit',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Memory limit in PHP ini format (e.g. 128M, 512M, 1G)',
            self::DEFAULT_MEMORY_LIMIT,
        );

        $this->addOption(
            'max-jobs',
            'j',
            InputOption::VALUE_OPTIONAL,
            'Maximum number of jobs to process before exiting (0 = unlimited)',
            (string) self::DEFAULT_MAX_JOBS,
        );

        $this->addOption(
            'worker-id',
            'w',
            InputOption::VALUE_OPTIONAL,
            'Unique worker identifier (auto-generated if empty)',
            '',
        );

        $this->addOption(
            'stop-when-empty',
            null,
            InputOption::VALUE_NONE,
            'Exit if no job is available after one polling cycle',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeout       = (int) $input->getOption('timeout');
        $memoryLimit   = (string) $input->getOption('memory-limit');
        $maxJobs       = (int) $input->getOption('max-jobs');
        $workerId      = $this->resolveWorkerId((string) $input->getOption('worker-id'));
        $stopWhenEmpty = (bool) $input->getOption('stop-when-empty');

        $memoryLimitBytes = $this->convertMemoryLimitToBytes($memoryLimit);
        $processedJobs    = 0;
        $exitCode         = self::SUCCESS;

        $this->installSignalHandlers();

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info('Worker started', [
                'worker_id'    => $workerId,
                'timeout'      => $timeout,
                'memory_limit' => $memoryLimit,
                'max_jobs'     => $maxJobs,
            ]);
        }

        $output->writeln(sprintf(
            '<info>Worker [%s] started (timeout: %ds, memory: %s, max-jobs: %d)</info>',
            $workerId,
            $timeout,
            $memoryLimit,
            $maxJobs,
        ));

        while (! $this->shouldShutdown) {
            try {
                // Recover stuck jobs before polling
                $this->recoverStuckJobs($workerId, $output);

                // Dispatch pending signals (belt-and-braces with pcntl_async_signals)
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Dequeue the next job (blocks up to --timeout seconds)
                $job = $this->jobRepository->dequeue($workerId, $timeout);

                if ($this->shouldShutdown) {
                    break;
                }

                if (! $job instanceof Job) {
                    if ($stopWhenEmpty) {
                        $output->writeln('  <comment>No job available, --stop-when-empty set, exiting</comment>');

                        break;
                    }

                    // No job available within this polling cycle — keep waiting
                    continue;
                }

                try {
                    $this->processJob($job, $output);
                } finally {
                    $this->runtimeResetter?->reset();
                }

                ++$processedJobs;

                // Check restart conditions
                if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                    $output->writeln(sprintf(
                        '<info>Worker [%s] reached max jobs (%d), restarting</info>',
                        $workerId,
                        $maxJobs,
                    ));

                    break;
                }

                if (memory_get_peak_usage(true) >= $memoryLimitBytes) {
                    $output->writeln(sprintf(
                        '<info>Worker [%s] reached memory limit, restarting</info>',
                        $workerId,
                    ));

                    if ($this->logger instanceof LoggerInterface) {
                        $this->logger->info('Memory limit reached, restarting worker', [
                            'worker_id'    => $workerId,
                            'memory_usage' => memory_get_peak_usage(true),
                            'memory_limit' => $memoryLimitBytes,
                        ]);
                    }

                    break;
                }
            } catch (WorkerRestartRequiredException $e) {
                $output->writeln(sprintf(
                    '<error>Worker [%s] lost a fail-closed resource and must restart: %s</error>',
                    $workerId,
                    $e->getMessage(),
                ));

                $this->logger?->critical('Worker is exiting for supervisor restart after lease loss.', [
                    'worker_id' => $workerId,
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
                $this->reportLoopFailure($e, $workerId, $processedJobs);
                $exitCode = self::FAILURE;

                break;
            } catch (Throwable $e) {
                $output->writeln(sprintf(
                    '<error>Worker [%s] error: %s</error>',
                    $workerId,
                    $e->getMessage(),
                ));

                if ($this->logger instanceof LoggerInterface) {
                    $this->logger->error('Worker loop error', [
                        'worker_id' => $workerId,
                        'exception' => $e::class,
                        'message'   => $e->getMessage(),
                    ]);
                }
                $this->reportLoopFailure($e, $workerId, $processedJobs);

                // Avoid tight loop on persistent errors
                sleep(5);
            }
        }

        $output->writeln(sprintf(
            '<info>Worker [%s] shutting down (processed: %d jobs)</info>',
            $workerId,
            $processedJobs,
        ));

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info('Worker shutting down', [
                'worker_id'      => $workerId,
                'processed_jobs' => $processedJobs,
            ]);
        }

        return $exitCode;
    }

    /**
     * @throws Throwable
     */
    private function processJob(Job $job, OutputInterface $output): void
    {
        $jobId = $job->getIdentifier()->toString();

        $output->writeln(sprintf(
            '  <comment>Processing job [%s] type: %s</comment>',
            $jobId,
            $job->type(),
        ));

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info('Processing job', [
                'job_id'  => $jobId,
                'type'    => $job->type(),
                'attempt' => $job->attemptCount(),
            ]);
        }

        // Let exceptions propagate to the outer loop. The job stays `running`
        // and will be recovered by findStuck(30) after the stuck timeout elapses
        // (up to 30 minutes from when it was dequeued).
        $this->jobProcessor->process($job);

        $output->writeln(sprintf(
            '  <info>Job [%s] completed (status: %s)</info>',
            $jobId,
            $job->status(),
        ));
    }

    private function recoverStuckJobs(string $workerId, OutputInterface $output): void
    {
        $stuckJobs = $this->jobRepository->findStuck(30);

        if ([] !== $stuckJobs) {
            $count = count($stuckJobs);

            $output->writeln(sprintf(
                '  <comment>Recovering %d stuck job(s)</comment>',
                $count,
            ));

            if ($this->logger instanceof LoggerInterface) {
                $this->logger->info('Recovering stuck jobs', [
                    'worker_id' => $workerId,
                    'count'     => $count,
                ]);
            }

            foreach ($stuckJobs as $stuckJob) {
                $stuckJob->canRetry()
                    ? $stuckJob->markPendingForRetry()
                    : $stuckJob->markFailed('Job exceeded the 30 minute running timeout.');
                $this->jobRepository->save($stuckJob);

                $output->writeln(sprintf(
                    '  <comment>  Recovered job [%s] type: %s (attempt: %d) -> %s</comment>',
                    $stuckJob->getIdentifier()->toString(),
                    $stuckJob->type(),
                    $stuckJob->attemptCount(),
                    $stuckJob->status(),
                ));
            }
        }
    }

    private function installSignalHandlers(): void
    {
        $handler = function(int $signal): void {
            $this->shouldShutdown = true;

            if ($this->logger instanceof LoggerInterface) {
                $this->logger->info('Worker received shutdown signal', [
                    'signal' => $signal,
                ]);
            }
        };

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGHUP, $handler);
        }
    }

    private function reportLoopFailure(Throwable $throwable, string $workerId, int $processedJobs): void
    {
        if (! $this->sentryLifecycle instanceof SentryLifecycle || ! $this->sentryReporter instanceof SentryReporter) {
            return;
        }

        try {
            $this->sentryLifecycle->withIsolatedScope(function() use ($throwable, $workerId, $processedJobs): void {
                $this->sentryReporter->setTag('component', 'worker');
                $this->sentryReporter->setTag('command', $this->getName() ?? 'worker:run');
                $this->sentryReporter->setTag('worker_id', $workerId);
                $this->sentryReporter->setContext('worker', [
                    'worker_id'      => $workerId,
                    'processed_jobs' => $processedJobs,
                ]);
                $this->sentryReporter->captureException($throwable);
            });
            $this->sentryLifecycle->flush();
        } catch (Throwable $reportingFailure) {
            $this->logger?->warning('Failed to report worker loop failure to Sentry', [
                'exception' => $reportingFailure,
            ]);
        }
    }

    private function resolveWorkerId(string $explicitId): string
    {
        if ('' !== $explicitId) {
            return $explicitId;
        }

        $hostname = gethostname();
        $pid      = getmypid();

        return sprintf('worker-%s-%s', $hostname, $pid);
    }

    private function convertMemoryLimitToBytes(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $unit  = substr($limit, -1);
        $value = (int) substr($limit, 0, -1);

        return match ($unit) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => (int) $limit,
        };
    }
}
