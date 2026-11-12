<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\SodiumSecretEncryptor;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\ClientInterface;
use Packio\Publication\Infrastructure\Bitbucket\BitbucketAccessCredentials;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;
use Packio\Publication\Infrastructure\GenericGit\HttpGenericGitComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use Packio\Publication\Infrastructure\Github\GithubAccessCredentials;
use Packio\Publication\Infrastructure\Github\HttpGithubComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;
use Packio\Publication\Infrastructure\Gitlab\HttpGitlabComposerMetadataFetcher;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use Packio\Publication\Infrastructure\GitlabSelfHosted\HttpGitlabSelfHostedComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Readme\Base64FileContentDecoder;
use Packio\Publication\Infrastructure\Readme\BitbucketPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\CodebergPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GenericGitPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GithubPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\MarkdownToHtmlRenderer;
use Packio\Publication\Infrastructure\Readme\ReadmeCandidateProvider;
use Packio\Publication\Infrastructure\Readme\ReadmeExtractorTemplate;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function base64_decode;
use function base64_encode;
use function chr;
use function ord;
use function strlen;
use function substr;

final class StoredSecretConsumerFailureTest extends TestCase
{
    #[DataProvider('credentialConsumers')]
    public function testMalformedEncryptedCredentialsFailBeforeProviderIo(string $consumer): void
    {
        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');
        $storedSecretResolver  = new StoredSecretResolver($sodiumSecretEncryptor);
        $stored                = $this->tamper($sodiumSecretEncryptor->encrypt('provider-token'));
        $http                  = $this->createMock(ClientInterface::class);
        $http->expects(self::never())->method('request');
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $brandingConfig = new BrandingConfig([
            'http_user_agent' => 'Packio/Tests',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stored provider credential could not be decrypted.');

        $this->callConsumer($consumer, new UpstreamHttpClient($http), $brandingConfig, $storedSecretResolver, $runner, $stored);
    }

    /** @return iterable<string, array{string}> */
    public static function credentialConsumers(): iterable
    {
        yield 'GitHub metadata' => ['github-metadata'];

        yield 'GitLab metadata' => ['gitlab-metadata'];

        yield 'generic Git metadata' => ['generic-git-metadata'];

        yield 'self-hosted GitLab metadata' => ['self-hosted-gitlab-metadata'];

        yield 'GitHub README' => ['github-readme'];

        yield 'GitLab README' => ['gitlab-readme'];

        yield 'self-hosted GitLab README' => ['self-hosted-gitlab-readme'];

        yield 'Codeberg README' => ['codeberg-readme'];

        yield 'Bitbucket README' => ['bitbucket-readme'];

        yield 'generic Git README' => ['generic-git-readme'];
    }

    private function callConsumer(
        string $consumer,
        UpstreamHttpClient $upstreamHttpClient,
        BrandingConfig $brandingConfig,
        StoredSecretResolver $storedSecretResolver,
        GitCommandRunnerInterface $gitCommandRunner,
        string $stored,
    ): void {
        $gitRemoteReferenceReader     = new GitRemoteReferenceReader($gitCommandRunner, OutboundPolicyFixture::git());
        $readmeExtractorTemplate      = new ReadmeExtractorTemplate(new ReadmeCandidateProvider(), new MarkdownToHtmlRenderer());

        match ($consumer) {
            'github-metadata'             => new HttpGithubComposerMetadataFetcher($upstreamHttpClient, $brandingConfig, $storedSecretResolver, $gitRemoteReferenceReader)
                ->snapshot('team', 'repository', new GithubAccessCredentials($stored)),
            'gitlab-metadata'             => new HttpGitlabComposerMetadataFetcher($upstreamHttpClient, $brandingConfig, $storedSecretResolver, $gitRemoteReferenceReader)
                ->snapshot('team', 'repository', new GitlabAccessCredentials($stored)),
            'generic-git-metadata'        => new HttpGenericGitComposerMetadataFetcher($upstreamHttpClient, $brandingConfig, $storedSecretResolver, $gitRemoteReferenceReader)
                ->snapshot('https://git.example.test/team/repository.git', new GenericGitAccessCredentials('https://git.example.test/team/repository.git', $stored)),
            'self-hosted-gitlab-metadata' => new HttpGitlabSelfHostedComposerMetadataFetcher($upstreamHttpClient, $brandingConfig, $storedSecretResolver, $gitRemoteReferenceReader)
                ->snapshot('team', 'repository', new GitlabSelfHostedAccessCredentials('https://gitlab.example.test', $stored)),
            'github-readme'               => new GithubPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, new Base64FileContentDecoder(), $storedSecretResolver)
                ->extractGithubReadmeHtml('team', 'repository', 'main', new GithubAccessCredentials($stored)),
            'gitlab-readme'               => new GitlabPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, new Base64FileContentDecoder(), $storedSecretResolver)
                ->extractGitlabReadmeHtml('team', 'repository', 'main', new GitlabAccessCredentials($stored)),
            'self-hosted-gitlab-readme'   => new GitlabSelfHostedPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, $storedSecretResolver)
                ->extractGitlabSelfHostedReadmeHtml('team', 'repository', 'main', new GitlabSelfHostedAccessCredentials('https://gitlab.example.test', $stored)),
            'codeberg-readme'             => new CodebergPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, new Base64FileContentDecoder(), $storedSecretResolver)
                ->extractCodebergReadmeHtml('team', 'repository', 'main', new CodebergAccessCredentials($stored)),
            'bitbucket-readme'            => new BitbucketPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, $storedSecretResolver)
                ->extractBitbucketReadmeHtml('team', 'repository', 'main', new BitbucketAccessCredentials($stored)),
            'generic-git-readme'          => new GenericGitPackageReadmeExtractor($upstreamHttpClient, $brandingConfig, $readmeExtractorTemplate, $storedSecretResolver)
                ->extractGenericGitReadmeHtml(
                    'https://git.example.test/team/repository.git',
                    'main',
                    new GenericGitAccessCredentials('https://git.example.test/team/repository.git', $stored),
                ),
            default                       => throw new RuntimeException('Unknown stored-secret consumer test case.'),
        };
    }

    private function tamper(string $envelope): string
    {
        $prefix  = 'secretbox:v1:';
        $decoded = base64_decode(substr($envelope, strlen($prefix)), true);
        self::assertIsString($decoded);
        $decoded[strlen($decoded) - 1] = chr(ord($decoded[strlen($decoded) - 1]) ^ 1);

        return $prefix . base64_encode($decoded);
    }
}
