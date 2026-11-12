<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Cli;

use Packio\Source\Application\Dto\SourceOAuthRefreshInlineResult;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Service\SourceOAuthRefreshMaintenanceService;
use Packio\Source\Application\Service\SourceOAuthRefreshPolicy;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_map;
use function array_slice;
use function sprintf;
use function trim;

final class SourceOAuthRefreshMaintenanceCommand extends Command
{
    public function __construct(
        private readonly SourceOAuthRefreshPolicy $sourceOAuthRefreshPolicy,
        private readonly SourceOAuthRefreshMaintenanceService $sourceOAuthRefreshMaintenanceService,
        private readonly OrganizationQueryPort $organizationQueryPort,
        private readonly ?SentryLifecycle $sentryLifecycle = null,
        private readonly ?SentryReporter $sentryReporter = null,
    ) {
        parent::__construct('source:oauth:refresh-maintenance');
        $this->setDescription('Refresh Source OAuth credentials before provider tokens go stale');
    }

    protected function configure(): void
    {
        $this->addOption(
            'provider',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Limit maintenance to a provider',
        );
        $this->addOption(
            'provider-instance',
            null,
            InputOption::VALUE_OPTIONAL,
            'Limit maintenance to a source provider instance id',
        );
        $this->addOption(
            'organization',
            'o',
            InputOption::VALUE_OPTIONAL,
            'Limit maintenance to an organization id or slug',
        );
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Maximum Source OAuth accounts to inspect',
        );
        $this->addOption(
            'refresh-before',
            null,
            InputOption::VALUE_OPTIONAL,
            'Refresh tokens expiring within this relative duration',
        );
        $this->addOption(
            'idle-before',
            null,
            InputOption::VALUE_OPTIONAL,
            'Refresh tokens whose account has not been used since this relative duration',
        );
        $this->addOption(
            'minimum-refresh-interval',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip accounts updated more recently than this relative duration',
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Print candidate counts without enqueueing jobs',
        );
        $this->addOption(
            'run-inline',
            null,
            InputOption::VALUE_NONE,
            'Refresh candidates immediately instead of enqueueing worker jobs',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $organizationId = $this->resolveOrganizationId(trim((string) $input->getOption('organization')));
        $criteria       = $this->sourceOAuthRefreshPolicy->criteria(
            provider: trim((string) $input->getOption('provider')),
            providerInstanceId: trim((string) $input->getOption('provider-instance')),
            organizationId: $organizationId,
            limit: $this->limit($input->getOption('limit')),
            refreshBefore: $this->optionalString($input->getOption('refresh-before')),
            idleBefore: $this->optionalString($input->getOption('idle-before')),
            minimumRefreshInterval: $this->optionalString($input->getOption('minimum-refresh-interval')),
        );

        if ((bool) $input->getOption('run-inline')) {
            $result = $this->sourceOAuthRefreshMaintenanceService->runInline($criteria);
            $output->writeln(sprintf(
                '<info>Source OAuth refresh inline run completed.</info> '
                . 'candidates=%d refreshed=%d orphaned=%d failed=%d',
                $result->candidateCount(),
                $result->refreshedCount(),
                $result->orphanedCount(),
                $result->failedCount(),
            ));

            $this->reportInlineFailures($result);

            return $result->failedCount() > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->sourceOAuthRefreshMaintenanceService->schedule($criteria, $dryRun);

        $output->writeln(sprintf(
            '<info>Source OAuth refresh maintenance completed.</info> candidates=%d %s=%d orphaned=%d',
            $result->candidateCount(),
            $dryRun ? 'dry_run' : 'scheduled',
            $result->scheduledCount(),
            $result->orphanedCount(),
        ));

        return Command::SUCCESS;
    }

    private function resolveOrganizationId(string $organization): ?string
    {
        if ('' === $organization) {
            return null;
        }

        return $this->organizationQueryPort->resolveOrganizationId($organization) ?? $organization;
    }

    private function limit(mixed $limit): ?int
    {
        $limit = trim((string) $limit);

        return '' !== $limit ? (int) $limit : null;
    }

    private function optionalString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' !== $value ? $value : null;
    }

    private function reportInlineFailures(SourceOAuthRefreshInlineResult $sourceOAuthRefreshInlineResult): void
    {
        if (0 === $sourceOAuthRefreshInlineResult->failedCount()) {
            return;
        }

        if (! $this->sentryLifecycle instanceof SentryLifecycle || ! $this->sentryReporter instanceof SentryReporter) {
            return;
        }

        try {
            $this->sentryLifecycle->withIsolatedScope(function() use ($sourceOAuthRefreshInlineResult): void {
                $this->sentryReporter->setTag('component', 'source_oauth_refresh');
                $this->sentryReporter->setTag('command', $this->getName() ?? 'source:oauth:refresh-maintenance');
                $this->sentryReporter->setTag('failed_count', (string) $sourceOAuthRefreshInlineResult->failedCount());
                $this->sentryReporter->setContext('source_oauth_refresh', [
                    'failed_count' => $sourceOAuthRefreshInlineResult->failedCount(),
                    'failures'     => array_map(
                        static fn (array $failure): array => [
                            'source_oauth_account_id' => $failure['candidate']->sourceOAuthAccountId,
                            'provider'                => $failure['candidate']->provider,
                            'provider_instance_id'    => $failure['candidate']->providerInstanceId,
                            'reason'                  => $failure['candidate']->reason->value,
                            'error'                   => $failure['error'],
                        ],
                        array_slice($sourceOAuthRefreshInlineResult->failed, 0, 10),
                    ),
                ]);
                $this->sentryReporter->captureMessage('Source OAuth refresh inline failures', 'warning');
            });
            $this->sentryLifecycle->flush();
        } catch (Throwable) {
        }
    }
}
