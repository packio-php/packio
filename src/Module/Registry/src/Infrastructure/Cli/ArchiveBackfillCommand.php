<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Cli;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Infrastructure\Adapter\Worker\StoreReleaseArchiveJobHandler;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;
use function trim;

final class ArchiveBackfillCommand extends Command
{
    use ArchiveCommandSupport;

    public function __construct(
        private readonly OrganizationApiFacadeInterface $organizationApiFacade,
        private readonly PackageQueryPort $packageQueryPort,
        private readonly ReleaseQueryPort $releaseQueryPort,
        private readonly ReleaseStorageMetadataPort $releaseStorageMetadataPort,
        private readonly JobApiFacadeInterface $jobApiFacade,
    ) {
        parent::__construct('storage:archives:backfill');
        $this->setDescription('Backfill private release archive storage jobs');
    }

    protected function configure(): void
    {
        $this->addOption(
            'organization',
            'o',
            InputOption::VALUE_REQUIRED,
            'Organization id or slug to backfill',
        );

        $this->addOption(
            'package',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Limit backfill to a single package name',
            '',
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Print matching releases without enqueueing jobs',
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Enqueue jobs even when release storage metadata already exists',
        );

        $this->addOption(
            'include-public',
            null,
            InputOption::VALUE_NONE,
            'Include public packages; by default only private packages are backfilled',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $organizationInput = trim((string) $input->getOption('organization'));
        if ('' === $organizationInput) {
            $output->writeln('<error>Option --organization is required.</error>');

            return Command::FAILURE;
        }

        $organization = $this->resolveOrganization($organizationInput);
        if (! $organization instanceof OrganizationDto) {
            $output->writeln(sprintf('<error>Organization "%s" was not found.</error>', $organizationInput));

            return Command::FAILURE;
        }

        $packageName   = trim((string) $input->getOption('package'));
        $dryRun        = (bool) $input->getOption('dry-run');
        $force         = (bool) $input->getOption('force');
        $includePublic = (bool) $input->getOption('include-public');

        $packages = $this->packages($organization->id, $packageName);
        if ([] === $packages) {
            $output->writeln('<comment>No packages matched.</comment>');

            return Command::SUCCESS;
        }

        $stats = [
            'packages'         => 0,
            'releases'         => 0,
            'skipped_public'   => 0,
            'skipped_metadata' => 0,
            'scheduled'        => 0,
            'dry_run'          => 0,
        ];

        $output->writeln(sprintf(
            '<info>Backfilling archive storage jobs for organization %s (%s)</info>',
            $organization->slug,
            $organization->id,
        ));

        foreach ($packages as $package) {
            ++$stats['packages'];

            if (! $includePublic && 'private' !== $package->visibility) {
                ++$stats['skipped_public'];
                $output->writeln(sprintf('  <comment>skip public package</comment> %s', $package->name));

                continue;
            }

            foreach ($this->releaseQueryPort->releasesForPackage($organization->id, $package->name) as $release) {
                ++$stats['releases'];

                if (! $force && $this->hasStorageMetadata($organization->id, $package->name, $release->version)) {
                    ++$stats['skipped_metadata'];
                    $output->writeln(sprintf('  <comment>skip stored</comment> %s %s', $package->name, $release->version));

                    continue;
                }

                if ($dryRun) {
                    ++$stats['dry_run'];
                    $output->writeln(sprintf('  <info>dry-run</info> %s %s', $package->name, $release->version));

                    continue;
                }

                $this->enqueueArchiveJob($organization->id, $package, $release);
                ++$stats['scheduled'];
                $output->writeln(sprintf('  <info>scheduled</info> %s %s', $package->name, $release->version));
            }
        }

        $output->writeln(sprintf(
            '<info>Done.</info> packages=%d releases=%d scheduled=%d dry_run=%d skipped_public=%d skipped_metadata=%d',
            $stats['packages'],
            $stats['releases'],
            $stats['scheduled'],
            $stats['dry_run'],
            $stats['skipped_public'],
            $stats['skipped_metadata'],
        ));

        return Command::SUCCESS;
    }

    private function hasStorageMetadata(string $organizationId, string $packageName, string $version): bool
    {
        try {
            return null !== $this->releaseStorageMetadataPort->findDistStorageMetadata($organizationId, $packageName, $version);
        } catch (Throwable) {
            return false;
        }
    }

    private function enqueueArchiveJob(string $organizationId, PackageData $packageData, ReleaseData $releaseData): void
    {
        $this->jobApiFacade->enqueueOnce(
            organizationId: $organizationId,
            type: StoreReleaseArchiveJobHandler::TYPE,
            idempotencyKey: sprintf(
                'archive:backfill:%s:%s:%s:%s',
                $organizationId,
                $packageData->name,
                $releaseData->version,
                $releaseData->reference,
            ),
            payload: [
                'organization_id' => $organizationId,
                'package_name'    => $packageData->name,
                'version'         => $releaseData->version,
                'reference'       => $releaseData->reference,
                'origin'          => 'backfill',
            ],
        );
    }
}
