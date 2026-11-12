<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GitlabSelfHosted;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Git\GitHttpAuthHeader;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use RuntimeException;

use function is_array;
use function json_decode;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function trim;

final readonly class HttpGitlabSelfHostedComposerMetadataFetcher implements GitlabSelfHostedComposerMetadataFetcherInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private StoredSecretResolver $storedSecretResolver,
        private GitRemoteReferenceReader $gitRemoteReferenceReader,
    ) {}

    public function snapshot(
        string $namespace,
        string $repository,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedRepositorySnapshot {
        $refs = $this->fetchGitRefs($namespace, $repository, $gitlabSelfHostedAccessCredentials);

        return new GitlabSelfHostedRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $namespace,
        string $repository,
        ?string $ref,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedPackageMetadata {
        $snapshot = $this->snapshot($namespace, $repository, $gitlabSelfHostedAccessCredentials);
        $refName  = null !== $ref && '' !== trim($ref)
            ? trim($ref)
            : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($namespace, $repository, $gitReference, $snapshot->defaultBranch, $gitlabSelfHostedAccessCredentials);
    }

    public function fetchReference(
        string $namespace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): GitlabSelfHostedPackageMetadata {
        return new GitlabSelfHostedPackageMetadata(
            namespace: $namespace,
            repository: $repository,
            baseUrl: $gitlabSelfHostedAccessCredentials->baseUrl,
            reference: $gitReference->name,
            composer: $this->fetchComposerJson(
                $namespace,
                $repository,
                $gitReference->name,
                $gitlabSelfHostedAccessCredentials,
            ),
            defaultBranch: $defaultBranch,
            commitReference: $gitReference->commit,
            refType: $gitReference->type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComposerJson(
        string $namespace,
        string $repository,
        string $reference,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): array {
        $apiBaseUrl = $gitlabSelfHostedAccessCredentials->apiBaseUrl();
        $url        = sprintf(
            '%s/projects/%s/repository/files/%s/raw?ref=%s',
            $apiBaseUrl,
            rawurlencode($namespace . '/' . $repository),
            rawurlencode('composer.json'),
            rawurlencode($reference),
        );

        $content = $this->fetchComposerJsonContent($url, $this->bearerHttpHeaders($gitlabSelfHostedAccessCredentials));
        if (false === $content && $gitlabSelfHostedAccessCredentials->hasToken()) {
            $content = $this->fetchComposerJsonContent($url, $this->privateTokenHttpHeaders($gitlabSelfHostedAccessCredentials));
        }

        if (false === $content) {
            throw new RuntimeException(
                sprintf('Failed to fetch remote metadata from "%s".', $url),
            );
        }

        $composer = json_decode($content, true);
        if (! is_array($composer)) {
            throw new RuntimeException(
                sprintf('composer.json from "%s" is not valid JSON.', $url),
            );
        }

        return $composer;
    }

    /**
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    private function fetchGitRefs(
        string $namespace,
        string $repository,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials
    ): array {
        $gitUrl = sprintf(
            '%s/%s/%s.git',
            rtrim($gitlabSelfHostedAccessCredentials->baseUrl, '/'),
            $namespace,
            $repository,
        );

        return $this->gitRemoteReferenceReader->lsRemote(
            $gitUrl,
            $this->gitHeaderValues($gitlabSelfHostedAccessCredentials),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function fetchComposerJsonContent(string $url, array $headers): false|string
    {
        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $headers);
        } catch (UpstreamHttpException) {
            return false;
        }

        if ($response->statusCode() >= 400) {
            return false;
        }

        return $response->body();
    }

    /**
     * @return list<string>
     */
    private function gitHeaderValues(GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials): array
    {
        if (! $gitlabSelfHostedAccessCredentials->hasToken()) {
            return [];
        }

        $plainToken = $this->resolveToken($gitlabSelfHostedAccessCredentials->token);

        return [
            GitHttpAuthHeader::basic('oauth2', $plainToken),
            GitHttpAuthHeader::basic('git', $plainToken),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bearerHttpHeaders(GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($gitlabSelfHostedAccessCredentials->hasToken()) {
            $token                    = $this->resolveToken($gitlabSelfHostedAccessCredentials->token);
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function privateTokenHttpHeaders(GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials): array
    {
        $token = $this->resolveToken($gitlabSelfHostedAccessCredentials->token);

        return [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'PRIVATE-TOKEN' => $token,
        ];
    }

    private function resolveToken(?string $storedToken): string
    {
        if (null === $storedToken || '' === trim($storedToken)) {
            return '';
        }

        $token = $this->storedSecretResolver->resolve($storedToken);
        if ('' === $token) {
            throw new RuntimeException('Stored provider credential could not be decrypted.');
        }

        return $token;
    }
}
