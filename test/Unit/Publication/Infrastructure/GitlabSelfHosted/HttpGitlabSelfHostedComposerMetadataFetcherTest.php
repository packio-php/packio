<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\GitlabSelfHosted;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\SodiumSecretEncryptor;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Git\GitCommandResult;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use Packio\Publication\Infrastructure\GitlabSelfHosted\HttpGitlabSelfHostedComposerMetadataFetcher;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

use function base64_decode;
use function base64_encode;
use function chr;
use function ord;
use function str_repeat;
use function strlen;
use function substr;

final class HttpGitlabSelfHostedComposerMetadataFetcherTest extends TestCase
{
    #[Test]
    public function fetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://gitlab.company.test/api/v4/projects/team%2Frepo/repository/files/composer.json/raw?ref=v1.0.0', self::anything())->willReturn(new Response(200, [], '{"name":"team/repo"}'));
        $httpGitlabSelfHostedComposerMetadataFetcher = new HttpGitlabSelfHostedComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $gitlabSelfHostedPackageMetadata = $httpGitlabSelfHostedComposerMetadataFetcher->fetchReference('team', 'repo', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main', new GitlabSelfHostedAccessCredentials('https://gitlab.company.test', 'source-token'));

        self::assertSame('team/repo', $gitlabSelfHostedPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $gitlabSelfHostedPackageMetadata->commitReference);
        self::assertSame('tag', $gitlabSelfHostedPackageMetadata->refType);
        self::assertSame('main', $gitlabSelfHostedPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $gitlabSelfHostedPackageMetadata->refName);
    }

    #[Test]
    public function gitHeaderValuesUseBasicAuthVariantsForGitlabSmartHttp(): void
    {
        $httpGitlabSelfHostedComposerMetadataFetcher = new HttpGitlabSelfHostedComposerMetadataFetcher(
            new UpstreamHttpClient(new Client()),
            new BrandingConfig([
                'http_user_agent' => 'Packio/Tests',
            ]),
            $this->storedSecretResolver(),
            $this->gitRemoteReferenceReader(),
        );

        $arguments = $this->invokePrivate(
            $httpGitlabSelfHostedComposerMetadataFetcher,
            'gitHeaderValues',
            new GitlabSelfHostedAccessCredentials('https://gitlab.company.test', 'source-token'),
        );

        self::assertSame([
            'Authorization: Basic ' . base64_encode('oauth2:source-token'),
            'Authorization: Basic ' . base64_encode('git:source-token'),
        ], $arguments);
    }

    #[Test]
    public function metadataHeadersSupportOAuthAndPersonalAccessTokens(): void
    {
        $httpGitlabSelfHostedComposerMetadataFetcher = new HttpGitlabSelfHostedComposerMetadataFetcher(
            new UpstreamHttpClient(new Client()),
            new BrandingConfig([
                'http_user_agent' => 'Packio/Tests',
            ]),
            $this->storedSecretResolver(),
            $this->gitRemoteReferenceReader(),
        );
        $gitlabSelfHostedAccessCredentials = new GitlabSelfHostedAccessCredentials(
            'https://gitlab.company.test',
            'source-token',
        );

        $bearerHeaders       = $this->invokePrivate($httpGitlabSelfHostedComposerMetadataFetcher, 'bearerHttpHeaders', $gitlabSelfHostedAccessCredentials);
        $privateTokenHeaders = $this->invokePrivate($httpGitlabSelfHostedComposerMetadataFetcher, 'privateTokenHttpHeaders', $gitlabSelfHostedAccessCredentials);

        self::assertSame('Bearer source-token', $bearerHeaders['Authorization'] ?? null);
        self::assertSame('source-token', $privateTokenHeaders['PRIVATE-TOKEN'] ?? null);
    }

    #[Test]
    #[DataProvider('invalidStoredTokens')]
    public function malformedStoredTokenFailsBeforeGitOrHttpRequests(string $storedToken): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::never())->method('request');
        $httpGitlabSelfHostedComposerMetadataFetcher = new HttpGitlabSelfHostedComposerMetadataFetcher(
            new UpstreamHttpClient($client),
            new BrandingConfig([
                'http_user_agent' => 'Packio/Tests',
            ]),
            new StoredSecretResolver(new SodiumSecretEncryptor('test-key')),
            new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()),
        );

        $this->expectException(RuntimeException::class);

        $httpGitlabSelfHostedComposerMetadataFetcher->fetch(
            'team',
            'repository',
            null,
            new GitlabSelfHostedAccessCredentials('https://gitlab.company.test', $storedToken),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidStoredTokens(): iterable
    {
        yield 'short encrypted envelope' => ['secretbox:v1:AA=='];

        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');

        yield 'tampered encrypted envelope' => [self::tamper($sodiumSecretEncryptor->encrypt('provider-token'))];

        yield 'wrong key' => [new SodiumSecretEncryptor('other-key')->encrypt('provider-token')];

        yield 'encrypted empty token' => [$sodiumSecretEncryptor->encrypt('')];
    }

    private static function tamper(string $envelope): string
    {
        $prefix  = 'secretbox:v1:';
        $decoded = base64_decode(substr($envelope, strlen($prefix)), true);
        self::assertIsString($decoded);
        $decoded[strlen($decoded) - 1] = chr(ord($decoded[strlen($decoded) - 1]) ^ 1);

        return $prefix . base64_encode($decoded);
    }

    private function invokePrivate(
        HttpGitlabSelfHostedComposerMetadataFetcher $httpGitlabSelfHostedComposerMetadataFetcher,
        string $method,
        mixed ...$arguments,
    ): mixed {
        $reflectionMethod = new ReflectionMethod($httpGitlabSelfHostedComposerMetadataFetcher, $method);

        return $reflectionMethod->invoke($httpGitlabSelfHostedComposerMetadataFetcher, ...$arguments);
    }

    private function storedSecretResolver(): StoredSecretResolver
    {
        return new StoredSecretResolver(new SecretEncryptorStub());
    }

    private function gitRemoteReferenceReader(): GitRemoteReferenceReader
    {
        return new GitRemoteReferenceReader(new class implements GitCommandRunnerInterface {
            public function run(string $command, array $environment = []): GitCommandResult
            {
                unset($command, $environment);

                return new GitCommandResult('', '', 0);
            }
        }, OutboundPolicyFixture::git());
    }
}
