<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Cli;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function basename;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strstr;
use function trim;

final class ArchiveDevCleanupCommand extends Command
{
    use ArchiveCommandSupport;

    public function __construct(
        private readonly OrganizationApiFacadeInterface $organizationApiFacade,
        private readonly PackageQueryPort $packageQueryPort,
        private readonly ReleaseQueryPort $releaseQueryPort,
        private readonly PackageArchiveStorageInterface $packageArchiveStorage,
        private readonly PackageArchiveKeyGenerator $packageArchiveKeyGenerator,
    ) {
        parent::__construct('storage:archives:cleanup-dev');
        $this->setDescription('Clean stale dev release archive objects');
    }

    protected function configure(): void
    {
        $this->addOption(
            'organization',
            'o',
            InputOption::VALUE_REQUIRED,
            'Organization id or slug to clean',
        );

        $this->addOption(
            'package',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Limit cleanup to a single package name',
            '',
        );

        $this->addOption(
            'include-public',
            null,
            InputOption::VALUE_NONE,
            'Include public packages; by default only private packages are scanned',
        );

        $this->addOption(
            'delete',
            null,
            InputOption::VALUE_NONE,
            'Delete matching stale dev archive objects; without this option the command is a dry-run',
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

        $delete        = (bool) $input->getOption('delete');
        $packageName   = trim((string) $input->getOption('package'));
        $includePublic = (bool) $input->getOption('include-public');
        $packages      = $this->packages($organization->id, $packageName);

        if ([] === $packages) {
            $output->writeln('<comment>No packages matched.</comment>');

            return Command::SUCCESS;
        }

        $stats = [
            'packages'        => 0,
            'objects'         => 0,
            'candidates'      => 0,
            'deleted'         => 0,
            'kept_current'    => 0,
            'skipped_public'  => 0,
            'skipped_non_dev' => 0,
        ];

        $output->writeln(sprintf(
            '<info>%s stale dev archive cleanup for organization %s (%s)</info>',
            $delete ? 'Running' : 'Dry-run',
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

            $currentKeys = $this->currentArchiveKeys($organization->id, $package);
            $prefix      = $this->packageArchiveKeyGenerator->packageDistPrefix($organization->id, $package->name);

            foreach ($this->packageArchiveStorage->list($prefix) as $object) {
                ++$stats['objects'];

                if (isset($currentKeys[$object->key])) {
                    ++$stats['kept_current'];

                    continue;
                }

                if (! $this->isDevArchiveKey($object->key)) {
                    ++$stats['skipped_non_dev'];

                    continue;
                }

                ++$stats['candidates'];
                if ($delete) {
                    $this->packageArchiveStorage->delete($object->key);
                    ++$stats['deleted'];
                    $output->writeln(sprintf('  <info>deleted</info> %s', $object->key));

                    continue;
                }

                $output->writeln(sprintf('  <info>dry-run delete</info> %s', $object->key));
            }
        }

        $output->writeln(sprintf(
            '<info>Done.</info> packages=%d objects=%d candidates=%d deleted=%d kept_current=%d skipped_public=%d skipped_non_dev=%d',
            $stats['packages'],
            $stats['objects'],
            $stats['candidates'],
            $stats['deleted'],
            $stats['kept_current'],
            $stats['skipped_public'],
            $stats['skipped_non_dev'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function currentArchiveKeys(string $organizationId, PackageData $packageData): array
    {
        $keys = [];

        foreach ($this->releaseQueryPort->releasesForPackage($organizationId, $packageData->name) as $releaseData) {
            $keys[$this->packageArchiveKeyGenerator->key(
                $organizationId,
                $packageData->name,
                $releaseData->version,
                $releaseData->reference,
                'zip',
            )] = true;
        }

        return $keys;
    }

    private function isDevArchiveKey(string $key): bool
    {
        $fileName       = basename($key);
        $versionSegment = str_contains($fileName, '_')
            ? (string) strstr($fileName, '_', true)
            : $fileName;

        return str_starts_with($versionSegment, 'dev-')
            || str_starts_with($versionSegment, 'version-')
            || str_ends_with($versionSegment, '-dev');
    }
}
