<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\GenericGit;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;
use Packio\Publication\Infrastructure\GenericGit\HttpGenericGitComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Git\GitCommandResult;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class HttpGenericGitComposerMetadataFetcherTest extends TestCase
{
    public function testFetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://git.example.test/team/repo/raw/v1.0.0/composer.json', self::anything())->willReturn(new Response(200, [], '{"name":"team/repo"}'));
        $httpGenericGitComposerMetadataFetcher = new HttpGenericGitComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $genericGitPackageMetadata = $httpGenericGitComposerMetadataFetcher->fetchReference('https://git.example.test/team/repo.git', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main', new GenericGitAccessCredentials('https://git.example.test/team/repo.git'));

        self::assertSame('team/repo', $genericGitPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $genericGitPackageMetadata->commitReference);
        self::assertSame('tag', $genericGitPackageMetadata->refType);
        self::assertSame('main', $genericGitPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $genericGitPackageMetadata->refName);
    }

    public function testLegacyUnsafePathCannotReachHttpTransport(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::never())->method('request');
        $this->expectException(InvalidArgumentException::class);
        $this->fetcher($client)->fetch('https://git.example.test/team/repo.git', 'feature/next', new GenericGitAccessCredentials('https://git.example.test/team/repo.git', composerJsonPath: '../admin?token=x'));
    }

    public function testReferenceAndFilePathAreEncodedAsUrlSegments(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://git.example.test/team/repo/raw/feature%2Fnext/packages/my%20package/composer.json', self::anything())->willReturn(new Response(200, [], '{"name":"team/repo"}'));
        $genericGitPackageMetadata = $this->fetcher($client)->fetch('https://git.example.test/team/repo.git', 'feature/next', new GenericGitAccessCredentials('https://git.example.test/team/repo.git', composerJsonPath: 'packages/my package/composer.json'));
        self::assertSame('team/repo', $genericGitPackageMetadata->composer['name']);
    }

    private function fetcher(ClientInterface $client): HttpGenericGitComposerMetadataFetcher
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::once())->method('run')->willReturn(new GitCommandResult("ref: refs/heads/feature/next\tHEAD\n" . str_repeat('a', 40) . "\trefs/heads/feature/next\n", '', 0));

        return new HttpGenericGitComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));
    }
}
