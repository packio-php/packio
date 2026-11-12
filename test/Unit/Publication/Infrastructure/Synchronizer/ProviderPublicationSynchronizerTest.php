<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Synchronizer;

use Common\Logging\SafeExceptionContext;
use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\ComposerVersionResolver;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProviderInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPackageMetadata;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationAdapterInterface;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderRepositorySnapshot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class ProviderPublicationSynchronizerTest extends TestCase
{
    #[Test]
    public function synchronizePublishesVersionsSyncsReadmeMarksSuccessAndRecordsAudit(): void
    {
        $fakeSynchronizerDependencies    = new FakeSynchronizerDependencies();
        $fakeComposerReleaseDateProvider = new FakeComposerReleaseDateProvider();
        $fakeProviderPublicationAdapter  = FakeProviderPublicationAdapter::withMetadata([
            'v1.0.0' => [
                'name' => 'acme/demo',
            ],
            'main'   => [
                'name' => 'acme/demo',
            ],
        ]);

        $publicationSyncResult = $this->synchronizer($fakeSynchronizerDependencies, $fakeComposerReleaseDateProvider)->synchronize(
            $this->source(),
            null,
            $fakeProviderPublicationAdapter,
        );

        self::assertSame('resolved-token', $fakeProviderPublicationAdapter->credentialsToken);
        self::assertSame('resolved-token', $fakeComposerReleaseDateProvider->accessToken);
        self::assertSame('succeeded', $publicationSyncResult->source['lastSyncStatus'] ?? null);
        self::assertSame('acme/demo', $publicationSyncResult->package['name']);
        self::assertTrue($publicationSyncResult->created);
        self::assertCount(2, $publicationSyncResult->releases);
        self::assertSame(['v1.0.0', 'main'], $fakeProviderPublicationAdapter->metadataRequests);
        self::assertSame(1, $fakeProviderPublicationAdapter->readmeCalls);
        self::assertSame('publication.fake.completed', $fakeSynchronizerDependencies->auditEvent);
        self::assertSame(['1.0.0', 'dev-main'], $fakeSynchronizerDependencies->auditContext['versions'] ?? null);
    }

    #[Test]
    public function synchronizeSkipsDuplicateResolvedVersions(): void
    {
        $fakeSynchronizerDependencies   = new FakeSynchronizerDependencies();
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withMetadata(
            [
                'tag-a' => [
                    'name'    => 'acme/demo',
                    'version' => '1.0.0',
                ],
                'tag-b' => [
                    'name'    => 'acme/demo',
                    'version' => '1.0.0',
                ],
            ],
            [new GitReference('tag-a', 'aaa', GitReference::TYPE_TAG), new GitReference('tag-b', 'bbb', GitReference::TYPE_TAG)],
            'tag-a',
        );

        $publicationSyncResult = $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);

        self::assertCount(1, $publicationSyncResult->releases);
        self::assertSame(1, $fakeSynchronizerDependencies->auditContext['skipped_references_count'] ?? null);
        self::assertSame(
            'Resolved Composer version "1.0.0" was already published from reference "tag-a".',
            $fakeSynchronizerDependencies->auditContext['skipped_references'][0]['error'] ?? null,
        );
    }

    #[Test]
    public function skippedReferenceErrorIsRedactedInAudit(): void
    {
        $message                        = 'GET https://x.test/repo?access_token=abc123 failed';
        $fakeSynchronizerDependencies   = new FakeSynchronizerDependencies();
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withFailures(
            [
                'v1.0.0' => $message,
            ],
            [new GitReference('v1.0.0', 'tag-commit', GitReference::TYPE_TAG), new GitReference('main', 'branch-commit', GitReference::TYPE_BRANCH)],
        );

        $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);

        $error = $fakeSynchronizerDependencies->auditContext['skipped_references'][0]['error'] ?? '';
        self::assertSame(SafeExceptionContext::redact($message), $error);
        self::assertStringNotContainsString('abc123', $error);
    }

    #[Test]
    public function synchronizeMarksFailedAndRethrowsFirstReferenceErrorWhenNoPackageCanBePublished(): void
    {
        $fakeSynchronizerDependencies   = new FakeSynchronizerDependencies();
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withFailures([
            'main' => 'metadata failed',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata failed');

        try {
            $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);
        } finally {
            self::assertSame('source-1', $fakeSynchronizerDependencies->failedSourceId);
            self::assertSame('metadata failed', $fakeSynchronizerDependencies->failedError);
        }
    }

    #[Test]
    public function synchronizeMarksFailedWhenSnapshotFailsBeforeReferenceLoop(): void
    {
        $fakeSynchronizerDependencies   = new FakeSynchronizerDependencies();
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withSnapshotFailure('snapshot failed');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('snapshot failed');

        try {
            $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);
        } finally {
            self::assertSame('source-1', $fakeSynchronizerDependencies->failedSourceId);
            self::assertSame('snapshot failed', $fakeSynchronizerDependencies->failedError);
        }
    }

    #[Test]
    public function synchronizeStoresRedactedFailureMessage(): void
    {
        $message                        = 'GET https://x.test/repo?access_token=abc123 failed';
        $fakeSynchronizerDependencies   = new FakeSynchronizerDependencies();
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withSnapshotFailure($message);

        try {
            $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);
            self::fail('Expected exception.');
        } catch (RuntimeException) {
            self::assertSame(SafeExceptionContext::redact($message), $fakeSynchronizerDependencies->failedError);
            self::assertStringNotContainsString('abc123', $fakeSynchronizerDependencies->failedError);
        }
    }

    #[Test]
    public function fetchMetadataReceivesSnapshotDefaultBranch(): void
    {
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withMetadata(
            [],
            [new GitReference('v1.0.0', 'tag-commit', GitReference::TYPE_TAG)],
            'develop',
        );

        $this->synchronizer(new FakeSynchronizerDependencies())->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);

        self::assertSame(['v1.0.0', 'develop'], $fakeProviderPublicationAdapter->metadataRequests);
        self::assertSame(['develop', 'develop'], $fakeProviderPublicationAdapter->defaultBranches);
    }

    #[Test]
    public function tagWinsOverBranchWithSameName(): void
    {
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withMetadata(
            [],
            [new GitReference('release', 'tag-commit', GitReference::TYPE_TAG)],
            branches: [new GitReference('release', 'branch-commit', GitReference::TYPE_BRANCH)],
        );

        $this->synchronizer(new FakeSynchronizerDependencies())->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);

        self::assertEquals(
            new GitReference('release', 'tag-commit', GitReference::TYPE_TAG),
            $fakeProviderPublicationAdapter->gitReferences['release'] ?? null,
        );
    }

    #[Test]
    public function missingDefaultBranchFallsBackToNameAsCommit(): void
    {
        $fakeProviderPublicationAdapter = FakeProviderPublicationAdapter::withMetadata(
            [],
            [new GitReference('v1.0.0', 'tag-commit', GitReference::TYPE_TAG)],
        );

        $this->synchronizer(new FakeSynchronizerDependencies())->synchronize($this->source(), 'token', $fakeProviderPublicationAdapter);

        self::assertEquals(
            new GitReference('main', 'main', GitReference::TYPE_BRANCH),
            $fakeProviderPublicationAdapter->gitReferences['main'] ?? null,
        );
    }

    #[Test]
    public function newPackageTakesVisibilityAndActivityFromFreshSource(): void
    {
        $fakeSynchronizerDependencies              = new FakeSynchronizerDependencies();
        $fakeSynchronizerDependencies->freshSource = [
            ...$this->source(),
            'visibility' => 'private',
            'archived'   => true,
        ];

        $this->synchronizer($fakeSynchronizerDependencies)->synchronize(
            [
                ...$this->source(),
                'visibility' => 'public',
            ],
            'token',
            FakeProviderPublicationAdapter::withMetadata([]),
        );

        self::assertSame('private', $fakeSynchronizerDependencies->upsertedPackageData['visibility'] ?? null);
        self::assertFalse($fakeSynchronizerDependencies->upsertedPackageData['active'] ?? null);
    }

    #[Test]
    public function existingPackageDoesNotRereadSource(): void
    {
        $fakeSynchronizerDependencies                  = new FakeSynchronizerDependencies();
        $fakeSynchronizerDependencies->existingPackage = [
            'name'       => 'acme/demo',
            'visibility' => 'private',
            'active'     => false,
            'createdAt'  => '2026-01-01T00:00:00+00:00',
        ];

        $this->synchronizer($fakeSynchronizerDependencies)->synchronize($this->source(), 'token', FakeProviderPublicationAdapter::withMetadata([]));

        self::assertSame(0, $fakeSynchronizerDependencies->findSourceCalls);
        self::assertSame('private', $fakeSynchronizerDependencies->upsertedPackageData['visibility'] ?? null);
        self::assertFalse($fakeSynchronizerDependencies->upsertedPackageData['active'] ?? null);
    }

    private function synchronizer(
        FakeSynchronizerDependencies $fakeSynchronizerDependencies,
        ?FakeComposerReleaseDateProvider $fakeComposerReleaseDateProvider = null,
    ): ProviderPublicationSynchronizer {
        return new ProviderPublicationSynchronizer(
            $fakeSynchronizerDependencies,
            new ComposerVersionResolver(),
            $fakeComposerReleaseDateProvider ?? new FakeComposerReleaseDateProvider(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function source(): array
    {
        return [
            'id'             => 'source-1',
            'url'            => 'https://example.test/acme/demo',
            'owner'          => 'acme',
            'repository'     => 'demo',
            'visibility'     => 'private',
            'organizationId' => 'org-1',
            'providerConfig' => [],
        ];
    }
}

final class FakeComposerReleaseDateProvider implements ComposerReleaseDateProviderInterface
{
    public ?string $accessToken = null;

    public function resolve(array $source, ?string $accessToken, string $fallbackReleasedAt): ComposerReleaseDateMap
    {
        $this->accessToken = $accessToken;

        return ComposerReleaseDateMap::fallback($fallbackReleasedAt);
    }
}

final class FakeSynchronizerDependencies implements SynchronizerDependenciesPort
{
    public ?string $failedSourceId = null;
    public ?string $failedError    = null;
    public ?string $auditEvent     = null;

    public int $findSourceCalls    = 0;

    /** @var null|array<string, mixed> */
    public ?array $freshSource = null;

    /** @var null|array<string, mixed> */
    public ?array $existingPackage = null;

    /** @var array<string, mixed> */
    public array $auditContext = [];

    /** @var array<string, mixed> */
    public array $upsertedPackageData = [];

    public function findSource(string $sourceId): ?array
    {
        ++$this->findSourceCalls;

        return $this->freshSource;
    }

    public function resolveSourceAuthorizationSecret(string $sourceId): string
    {
        return 'resolved-token';
    }

    public function markSyncSucceeded(string $sourceId): array
    {
        return [
            'id'             => $sourceId,
            'url'            => 'https://example.test/acme/demo',
            'organizationId' => 'org-1',
            'lastSyncStatus' => 'succeeded',
        ];
    }

    public function markSyncFailed(string $sourceId, string $error): ?array
    {
        $this->failedSourceId = $sourceId;
        $this->failedError    = $error;

        return null;
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?array
    {
        return $this->existingPackage;
    }

    public function upsertPackage(array $packageData): array
    {
        $this->upsertedPackageData = $packageData;

        return $packageData + [
            'active'        => true,
            'downloadCount' => 0,
        ];
    }

    public function updatePackageReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?array
    {
        return null;
    }

    public function publishRelease(array $releaseData): array
    {
        return $releaseData + [
            'active'        => true,
            'downloadCount' => 0,
        ];
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
    ): void {
        $this->auditEvent   = $event;
        $this->auditContext = $context;
    }
}

final class FakeProviderPublicationAdapter implements ProviderPublicationAdapterInterface
{
    public ?string $credentialsToken = null;
    public int $readmeCalls          = 0;

    /** @var list<string> */
    public array $metadataRequests = [];

    /** @var list<string> */
    public array $defaultBranches = [];

    /** @var array<string, GitReference> */
    public array $gitReferences = [];

    /**
     * @param array<string, array<string, mixed>> $metadataByReference
     * @param list<GitReference|string>           $references
     * @param array<string, string>               $failures
     * @param list<GitReference>                  $branches
     */
    private function __construct(
        private array $metadataByReference,
        private readonly array $references,
        private readonly string $defaultBranch,
        private array $failures = [],
        private readonly string $snapshotFailure = '',
        private readonly array $branches = [],
    ) {}

    /**
     * @param array<string, array<string, mixed>> $metadataByReference
     * @param list<GitReference|string>           $references
     * @param list<GitReference>                  $branches
     */
    public static function withMetadata(
        array $metadataByReference,
        array $references = [new GitReference('v1.0.0', 'tag-commit', GitReference::TYPE_TAG), new GitReference('main', 'branch-commit', GitReference::TYPE_BRANCH)],
        string $defaultBranch = 'main',
        array $branches = [],
    ): self {
        return new self($metadataByReference, $references, $defaultBranch, branches: $branches);
    }

    /**
     * @param array<string, string> $failures
     * @param list<GitReference>    $references
     */
    public static function withFailures(
        array $failures,
        array $references = [new GitReference('main', 'branch-commit', GitReference::TYPE_BRANCH)],
    ): self {
        return new self([], $references, 'main', $failures);
    }

    public static function withSnapshotFailure(string $message): self
    {
        return new self([], [], 'main', [], $message);
    }

    public function provider(): string
    {
        return 'fake';
    }

    public function credentials(array $source, ?string $accessToken): object
    {
        $credentials        = new stdClass();
        $credentials->token = $accessToken;

        $this->credentialsToken = $accessToken;

        return $credentials;
    }

    public function accessToken(object $credentials): ?string
    {
        return $credentials->token ?? null;
    }

    public function snapshot(array $source, object $credentials): ProviderRepositorySnapshot
    {
        if ('' !== $this->snapshotFailure) {
            throw new RuntimeException($this->snapshotFailure);
        }

        return new ProviderRepositorySnapshot($this->defaultBranch, $this->references, $this->branches);
    }

    public function fetchMetadata(
        array $source,
        GitReference $gitReference,
        string $defaultBranch,
        object $credentials
    ): ProviderPackageMetadata {
        $this->metadataRequests[]                 = $gitReference->name;
        $this->defaultBranches[]                  = $defaultBranch;
        $this->gitReferences[$gitReference->name] = $gitReference;

        if (isset($this->failures[$gitReference->name])) {
            throw new RuntimeException($this->failures[$gitReference->name]);
        }

        $composer = $this->metadataByReference[$gitReference->name] ?? [
            'name' => 'acme/demo',
        ];

        return new ProviderPackageMetadata(
            new stdClass(),
            $composer,
            $gitReference->name,
            $this->defaultBranch,
            $gitReference->commit,
            $gitReference->type,
        );
    }

    public function sourceUrl(ProviderPackageMetadata $providerPackageMetadata): string
    {
        return 'https://example.test/acme/demo';
    }

    public function releasePayload(
        ProviderPackageMetadata $providerPackageMetadata,
        array $package,
        string $createdAt,
        string $sourceId,
        ComposerReleaseDateMap $composerReleaseDateMap,
        string $version,
    ): array {
        return [
            'packageName'       => $package['name'],
            'version'           => $version,
            'reference'         => $providerPackageMetadata->commitReference,
            'upstreamReference' => $providerPackageMetadata->refName,
            'referenceType'     => $providerPackageMetadata->refType,
            'composer'          => $providerPackageMetadata->composer,
            'source'            => [
                'source_id' => $sourceId,
                'type'      => 'git',
                'url'       => 'https://example.test/acme/demo.git',
                'reference' => $providerPackageMetadata->commitReference,
            ],
            'dist'              => [
                'type'      => 'zip',
                'url'       => 'https://example.test/acme/demo.zip',
                'reference' => $providerPackageMetadata->commitReference,
            ],
            'createdAt'         => $createdAt,
            'releasedAt'        => $composerReleaseDateMap->find($version, $providerPackageMetadata->refName),
            'organizationId'    => $package['organizationId'],
        ];
    }

    public function syncReadme(
        array $source,
        string $packageName,
        string $defaultBranch,
        object $credentials,
    ): array {
        ++$this->readmeCalls;

        return [
            'status'  => 'updated',
            'message' => 'README synchronized.',
        ];
    }
}
