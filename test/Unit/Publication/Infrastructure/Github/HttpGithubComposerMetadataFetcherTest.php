<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Github;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use Packio\Publication\Infrastructure\Github\HttpGithubComposerMetadataFetcher;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function json_encode;
use function str_repeat;

#[CoversClass(HttpGithubComposerMetadataFetcher::class)]
final class HttpGithubComposerMetadataFetcherTest extends TestCase
{
    #[Test]
    public function fetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://api.github.com/repos/team/repo/contents/composer.json?ref=v1.0.0', self::anything())->willReturn(new Response(200, [], json_encode([
            'content' => base64_encode('{"name":"team/repo"}'),
        ], JSON_THROW_ON_ERROR)));
        $httpGithubComposerMetadataFetcher = new HttpGithubComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $githubPackageMetadata = $httpGithubComposerMetadataFetcher->fetchReference('team', 'repo', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main');

        self::assertSame('team/repo', $githubPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $githubPackageMetadata->commitReference);
        self::assertSame('tag', $githubPackageMetadata->refType);
        self::assertSame('main', $githubPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $githubPackageMetadata->refName);
    }
}
