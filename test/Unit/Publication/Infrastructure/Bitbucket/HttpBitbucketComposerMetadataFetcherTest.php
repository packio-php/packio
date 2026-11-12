<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Bitbucket;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Bitbucket\HttpBitbucketComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(HttpBitbucketComposerMetadataFetcher::class)]
final class HttpBitbucketComposerMetadataFetcherTest extends TestCase
{
    #[Test]
    public function fetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://api.bitbucket.org/2.0/repositories/team/repo/src/v1.0.0/composer.json', self::anything())->willReturn(new Response(200, [], '{"name":"team/repo"}'));
        $httpBitbucketComposerMetadataFetcher = new HttpBitbucketComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $bitbucketPackageMetadata = $httpBitbucketComposerMetadataFetcher->fetchReference('team', 'repo', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main');

        self::assertSame('team/repo', $bitbucketPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $bitbucketPackageMetadata->commitReference);
        self::assertSame('tag', $bitbucketPackageMetadata->refType);
        self::assertSame('main', $bitbucketPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $bitbucketPackageMetadata->refName);
    }
}
