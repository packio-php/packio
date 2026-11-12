<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Service;

use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Psr\Log\LoggerInterface;
use Throwable;

use function strlen;

final readonly class PackageReadmeSyncService
{
    public function __construct(
        private SynchronizerDependenciesPort $synchronizerDependenciesPort,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * @param callable(): ?string            $extractReadmeHtml
     * @param null|callable(?string): void   $afterExtract
     * @param null|callable(Throwable): void $onFailure
     *
     * @return array{status: string, message: string}
     */
    public function synchronize(
        string $organizationId,
        string $packageName,
        callable $extractReadmeHtml,
        ?callable $afterExtract = null,
        ?callable $onFailure = null,
    ): array {
        try {
            $this->logger?->info('Package README sync started', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
            ]);

            $readmeHtml = $extractReadmeHtml();
            if (null === $readmeHtml) {
                $this->logger?->info('Package README not found upstream', [
                    'organization_id' => $organizationId,
                    'package_name'    => $packageName,
                ]);
            } else {
                $this->logger?->info('Package README extracted', [
                    'organization_id' => $organizationId,
                    'package_name'    => $packageName,
                    'readme_length'   => strlen($readmeHtml),
                ]);
            }

            if (null !== $afterExtract) {
                $afterExtract($readmeHtml);
            }

            $this->synchronizerDependenciesPort->updatePackageReadmeHtml(
                $organizationId,
                $packageName,
                $readmeHtml,
            );

            return [
                'status'  => null === $readmeHtml ? 'cleared' : 'updated',
                'message' => null === $readmeHtml
                    ? 'README was not found upstream; stored README was cleared.'
                    : 'README synchronized successfully.',
            ];
        } catch (Throwable $throwable) {
            $this->logger?->warning('Package README sync failed', [
                'organization_id' => $organizationId,
                'package_name'    => $packageName,
                'exception'       => $throwable,
            ]);

            if (null !== $onFailure) {
                $onFailure($throwable);
            }

            return [
                'status'  => 'failed',
                'message' => $throwable->getMessage(),
            ];
        }
    }
}
