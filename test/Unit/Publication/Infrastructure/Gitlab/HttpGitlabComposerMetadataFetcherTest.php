<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Gitlab;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use Packio\Publication\Infrastructure\Gitlab\HttpGitlabComposerMetadataFetcher;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(HttpGitlabComposerMetadataFetcher::class)]
final class HttpGitlabComposerMetadataFetcherTest extends TestCase
{
    #[Test]
    public function fetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://gitlab.com/api/v4/projects/team%2Frepo/repository/files/composer.json/raw?ref=v1.0.0', self::anything())->willReturn(new Response(200, [], '{"name":"team/repo"}'));
        $httpGitlabComposerMetadataFetcher = new HttpGitlabComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $gitlabPackageMetadata = $httpGitlabComposerMetadataFetcher->fetchReference('team', 'repo', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main');

        self::assertSame('team/repo', $gitlabPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $gitlabPackageMetadata->commitReference);
        self::assertSame('tag', $gitlabPackageMetadata->refType);
        self::assertSame('main', $gitlabPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $gitlabPackageMetadata->refName);
    }
}
