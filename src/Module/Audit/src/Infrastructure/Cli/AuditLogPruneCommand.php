<?php

declare(strict_types=1);

namespace Packio\Audit\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function ctype_digit;
use function is_scalar;
use function sprintf;

final class AuditLogPruneCommand extends Command
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly AuditRepositoryInterface $auditRepository,
        private readonly array $config,
    ) {
        parent::__construct('audit:log:prune');
        $this->setDescription('Delete old audit log entries');
    }

    protected function configure(): void
    {
        $this->addOption('older-than-days', null, InputOption::VALUE_REQUIRED, 'Delete entries older than this many days (default: AUDIT_LOG_RETENTION_DAYS)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the entries without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('older-than-days') ?? $this->config['audit']['retention_days'] ?? null;
        $days = is_scalar($days) ? (string) $days : '';
        if (! ctype_digit($days) || 0 === (int) $days) {
            $output->writeln('<error>--older-than-days / AUDIT_LOG_RETENTION_DAYS must be a positive integer.</error>');

            return Command::INVALID;
        }

        $before = Chronos::now('UTC')->subDays((int) $days);
        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('<info>Audit log prune dry run.</info> older_than_days=%s dry_run=%d', $days, $this->auditRepository->countOlderThan($before)));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Audit log pruned.</info> older_than_days=%s deleted=%d', $days, $this->auditRepository->deleteOlderThan($before)));

        return Command::SUCCESS;
    }
}
