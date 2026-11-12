<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Cli;

use const FILTER_VALIDATE_INT;

use InvalidArgumentException;
use Packio\Application\Security\RateLimiter\FileRateLimiter;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function filter_var;
use function is_string;
use function sprintf;

final class RateLimitCleanupCommand extends Command
{
    public function __construct(private readonly FileRateLimiter $fileRateLimiter)
    {
        parent::__construct('rate-limit:cleanup');
        $this->setDescription('Clean expired rate limiter files under the shared storage lock');
    }

    protected function configure(): void
    {
        $this->addOption('retention-seconds', null, InputOption::VALUE_REQUIRED, 'Confirmed retention covering current and historical windows');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum candidates (1..1000)', '1000');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report candidates without changing storage or cursor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retention = $input->getOption('retention-seconds');
        $limit     = $input->getOption('limit');
        $retention = is_string($retention) ? filter_var($retention, FILTER_VALIDATE_INT) : false;
        $limit     = is_string($limit) ? filter_var($limit, FILTER_VALIDATE_INT) : false;
        if (false === $retention || $retention < 1 || false === $limit || $limit < 1 || $limit > 1000) {
            $output->writeln('<error>Provide positive --retention-seconds and --limit between 1 and 1000.</error>');

            return self::INVALID;
        }

        try {
            $result = $this->fileRateLimiter->cleanup($retention, $limit, true === $input->getOption('dry-run'));
        } catch (InvalidArgumentException) {
            $output->writeln('<error>Retention must cover the configured rate limiter retention.</error>');

            return self::INVALID;
        } catch (RuntimeException) {
            $output->writeln('<error>Rate limiter storage cleanup failed.</error>');

            return self::FAILURE;
        }
        $output->writeln(sprintf('scanned=%d deleted=%d skipped=%d', $result['scanned'], $result['deleted'], $result['skipped']));

        return self::SUCCESS;
    }
}
