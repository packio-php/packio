<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Facade;

use Packio\Publication\Api\Dto\PublicationSyncResultDto;
use Packio\Publication\Api\PublicationApiFacadeInterface;
use Packio\Publication\Application\Command\ConnectSourceService;
use Packio\Publication\Application\Dto\PublicationSyncResult;

final readonly class PublicationApiFacade implements PublicationApiFacadeInterface
{
    public function __construct(
        private ConnectSourceService $connectSourceService,
    ) {}

    public function synchronizeSource(string $sourceId): PublicationSyncResultDto
    {
        return $this->toApiResult(
            $this->connectSourceService->synchronizeSource($sourceId),
        );
    }

    private function toApiResult(PublicationSyncResult $publicationSyncResult): PublicationSyncResultDto
    {
        return new PublicationSyncResultDto(
            packageName: (string) ($publicationSyncResult->package['name'] ?? ''),
            releases: $publicationSyncResult->releases,
            created: $publicationSyncResult->created,
            readmeSync: $this->publicReadmeSync($publicationSyncResult->readmeSync),
            retention: $publicationSyncResult->retention,
        );
    }

    /**
     * @param array<string, mixed> $readmeSync
     *
     * @return array<string, mixed>
     */
    private function publicReadmeSync(array $readmeSync): array
    {
        $status = (string) ($readmeSync['status'] ?? '');
        if ('' === $status) {
            return [];
        }

        return [
            'status'  => $status,
            'message' => match ($status) {
                'updated' => 'README synchronized successfully.',
                'cleared' => 'README was not found upstream; stored README was cleared.',
                'skipped' => 'README synchronization was skipped.',
                'failed'  => 'README synchronization failed. Existing README was left unchanged.',
                default   => 'README synchronization status recorded.',
            },
        ];
    }
}
