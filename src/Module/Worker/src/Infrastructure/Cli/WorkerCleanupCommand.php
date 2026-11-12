<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Cli;

use Packio\Worker\Application\Command\WorkerMaintenanceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class WorkerCleanupCommand extends Command
{
    public function __construct(private readonly WorkerMaintenanceService $workerMaintenanceService)
    {
        parent::__construct('worker:cleanup');
        $this->setDescription('Clean up old jobs and job logs');
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Retention days for jobs/logs',
            30,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days        = (int) $input->getOption('days');
        $deletedJobs = $this->workerMaintenanceService->cleanupOldJobs($days);
        $deletedLogs = $this->workerMaintenanceService->cleanupOldJobLogs($days);

        $output->writeln(sprintf('<info>Deleted %d job(s) and %d log(s)</info>', $deletedJobs, $deletedLogs));

        return self::SUCCESS;
    }
}
