<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Cli;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function sprintf;

final class ReleaseOrphansCleanupCommand extends Command
{
    public function __construct(
        private readonly ReleaseRepositoryInterface $releaseRepository,
        private readonly ReleaseApiFacadeInterface $releaseApiFacade,
        private readonly PackageApiFacadeInterface $packageApiFacade,
        private readonly ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade,
    ) {
        parent::__construct('release:orphans:cleanup');
        $this->setDescription('Delete releases whose package no longer exists');
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count orphaned releases without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun  = (bool) $input->getOption('dry-run');
        $found   = 0;
        $deleted = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($this->releaseRepository->findPackageKeys() as $key) {
            $organizationId = $key['organization_id'];
            $packageName    = $key['package_name'];
            if ($this->packageApiFacade->findPackageInOrganization($organizationId, $packageName) instanceof PackageDto) {
                continue;
            }

            $count = count($this->releaseApiFacade->releasesForPackage($packageName, true, $organizationId));
            $found += $count;
            if ($dryRun) {
                continue;
            }

            // Same order as ReleasesDeleteHandler: schedule archive cleanup before the rows that locate the archives go.
            try {
                $archiveCleanup = $this->archiveStorageCleanupApiFacade->schedulePackageArchiveCleanup($organizationId, $packageName, null, 'cli');
            } catch (Throwable) {
                $failed += $count;

                continue;
            }

            if (0 < $archiveCleanup['failed']) {
                $failed += $count;

                continue;
            }

            // The package may have been created since the first check; then its releases are not orphans.
            if ($this->packageApiFacade->findPackageInOrganization($organizationId, $packageName) instanceof PackageDto) {
                $skipped += $count;

                continue;
            }

            $deleted += $this->releaseApiFacade->deleteReleasesForPackage($packageName, $organizationId);
        }

        $output->writeln(sprintf('<info>Orphaned releases cleanup completed.</info> found=%d deleted=%d skipped=%d', $found, $deleted, $skipped));
        if (0 < $failed) {
            $output->writeln(sprintf('<error>Archive cleanup could not be scheduled for %d releases; they were kept.</error>', $failed));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
