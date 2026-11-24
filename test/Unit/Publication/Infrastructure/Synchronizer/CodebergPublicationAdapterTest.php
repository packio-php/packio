<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Synchronizer;

use Packio\Publication\Application\Port\SynchronizerDependenciesPort;
use Packio\Publication\Application\Service\PackageReadmeSyncService;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use Packio\Publication\Infrastructure\Codeberg\CodebergComposerMetadataFetcherInterface;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractorInterface;
use Packio\Publication\Infrastructure\Synchronizer\CodebergPublicationAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodebergPublicationAdapter::class)]
final class CodebergPublicationAdapterTest extends TestCase
{
    #[Test]
    public function credentialsTakeBaseUrlFromProviderConfigAndProviderIsConfigurable(): void
    {
        $adapter = new CodebergPublicationAdapter(
            $this->createStub(CodebergComposerMetadataFetcherInterface::class),
            $this->createStub(CodebergPackageReadmeExtractorInterface::class),
            new PackageReadmeSyncService($this->createStub(SynchronizerDependenciesPort::class)),
            'forgejo_self_hosted',
        );

        $credentials = $adapter->credentials([
            'providerConfig' => [
                'base_url' => 'https://example.com/git',
            ],
        ], 'token');

        self::assertInstanceOf(CodebergAccessCredentials::class, $credentials);
        self::assertSame('https://example.com/git', $credentials->baseUrl);
        self::assertSame('forgejo_self_hosted', $adapter->provider());
    }

    #[Test]
    public function credentialsDefaultToCodeberg(): void
    {
        $adapter = new CodebergPublicationAdapter(
            $this->createStub(CodebergComposerMetadataFetcherInterface::class),
            $this->createStub(CodebergPackageReadmeExtractorInterface::class),
            new PackageReadmeSyncService($this->createStub(SynchronizerDependenciesPort::class)),
        );

        $credentials = $adapter->credentials([
            'providerConfig' => [],
        ], 'token');

        self::assertInstanceOf(CodebergAccessCredentials::class, $credentials);
        self::assertSame('https://codeberg.org', $credentials->baseUrl);
        self::assertSame('codeberg', $adapter->provider());
    }

    #[Test]
    public function codebergIgnoresBaseUrlFromProviderConfig(): void
    {
        $adapter = new CodebergPublicationAdapter(
            $this->createStub(CodebergComposerMetadataFetcherInterface::class),
            $this->createStub(CodebergPackageReadmeExtractorInterface::class),
            new PackageReadmeSyncService($this->createStub(SynchronizerDependenciesPort::class)),
        );

        $credentials = $adapter->credentials([
            'providerConfig' => [
                'base_url' => 'https://evil.example',
            ],
        ], 'token');

        self::assertInstanceOf(CodebergAccessCredentials::class, $credentials);
        self::assertSame('https://codeberg.org', $credentials->baseUrl);
    }
}
