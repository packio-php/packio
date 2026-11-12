<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Facade;

use Packio\Publication\Application\Command\ConnectSourceService;
use Packio\Publication\Application\Dto\PublicationSyncResult;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerInterface;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerRegistry;
use Packio\Publication\Infrastructure\Facade\PublicationApiFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PublicationApiFacade::class)]
final class PublicationApiFacadeTest extends TestCase
{
    #[Test]
    public function synchronizeSourceReturnsPublicSafeReadmeSyncMessage(): void
    {
        $publicationApiFacade = new PublicationApiFacade(new ConnectSourceService(
            new SourcePublicationSynchronizerRegistry([
                new class implements SourcePublicationSynchronizerInterface {
                    public function provider(): string
                    {
                        return 'github';
                    }

                    public function synchronize(array $source, ?string $accessToken = null): PublicationSyncResult
                    {
                        return new PublicationSyncResult(
                            source: $source,
                            package: [
                                'name'           => 'vendor/package',
                                'organizationId' => 'org-1',
                            ],
                            releases: [],
                            created: false,
                            readmeSync: [
                                'status'  => 'failed',
                                'message' => 'GitLab README API error (500) while fetching "https://gitlab.com/api/v4/projects/private".',
                            ],
                        );
                    }
                },
            ]),
            new class implements SynchronizerDependenciesPort {
                public function findSource(string $sourceId): ?array
                {
                    return '' === $sourceId ? null : [
                        'id'             => $sourceId,
                        'provider'       => 'github',
                        'organizationId' => 'org-1',
                    ];
                }

                public function resolveSourceAuthorizationSecret(string $sourceId): ?string
                {
                    return null;
                }

                public function markSyncSucceeded(string $sourceId): ?array
                {
                    return null;
                }

                public function markSyncFailed(string $sourceId, string $error): ?array
                {
                    return null;
                }

                public function findPackageInOrganization(string $organizationId, string $name): ?array
                {
                    return null;
                }

                public function upsertPackage(array $packageData): array
                {
                    return $packageData;
                }

                public function updatePackageReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?array
                {
                    return null;
                }

                public function publishRelease(array $releaseData): array
                {
                    return $releaseData;
                }

                public function applyReleaseRetention(
                    string $organizationId,
                    string $sourceId,
                    string $packageName,
                    int $keepLastReleases
                ): array {
                    return [];
                }

                public function recordAudit(
                    string $event,
                    array $context,
                    string $organizationId,
                    string $sourceId = '',
                    string $packageName = '',
                ): void {}
            },
        ));

        $publicationSyncResultDto = $publicationApiFacade->synchronizeSource('source-1');

        self::assertSame('failed', $publicationSyncResultDto->readmeSync['status'] ?? null);
        self::assertSame(
            'README synchronization failed. Existing README was left unchanged.',
            $publicationSyncResultDto->readmeSync['message'] ?? null,
        );
    }

    #[Test]
    public function synchronizeSourceReturnsPackageNameAndReleaseVersions(): void
    {
        $synchronizerDependenciesPort = $this->createStub(SynchronizerDependenciesPort::class);
        $synchronizerDependenciesPort->method('findSource')->willReturn([
            'id'             => 'source-1',
            'provider'       => 'github',
            'organizationId' => 'org-1',
        ]);
        $synchronizerDependenciesPort->method('applyReleaseRetention')->willReturn([
            'kept' => 3,
        ]);

        $publicationApiFacade = new PublicationApiFacade(new ConnectSourceService(
            new SourcePublicationSynchronizerRegistry([
                new class implements SourcePublicationSynchronizerInterface {
                    public function provider(): string
                    {
                        return 'github';
                    }

                    public function synchronize(array $source, ?string $accessToken = null): PublicationSyncResult
                    {
                        return new PublicationSyncResult(
                            source: $source,
                            package: [
                                'name'           => 'team/repo',
                                'organizationId' => 'org-1',
                            ],
                            releases: [[
                                'version'   => '1.0.0',
                                'reference' => 'abc',
                            ]],
                            created: true,
                        );
                    }
                },
            ]),
            $synchronizerDependenciesPort,
        ));

        $publicationSyncResultDto = $publicationApiFacade->synchronizeSource('source-1');

        self::assertSame('team/repo', $publicationSyncResultDto->packageName);
        self::assertSame('1.0.0', $publicationSyncResultDto->releases[0]['version']);
        self::assertSame('abc', $publicationSyncResultDto->releases[0]['reference']);
        self::assertTrue($publicationSyncResultDto->created);
        self::assertSame([
            'kept' => 3,
        ], $publicationSyncResultDto->retention);
    }

    #[Test]
    public function synchronizeSourceThrowsWhenSourceMissing(): void
    {
        $synchronizerDependenciesPort = $this->createStub(SynchronizerDependenciesPort::class);
        $synchronizerDependenciesPort->method('findSource')->willReturn(null);

        $publicationApiFacade = new PublicationApiFacade(
            new ConnectSourceService(new SourcePublicationSynchronizerRegistry([]), $synchronizerDependenciesPort),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source not found.');

        $publicationApiFacade->synchronizeSource('missing');
    }
}
