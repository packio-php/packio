<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Gitlab;

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
use function sprintf;
use function trim;

final readonly class HttpGitlabComposerMetadataFetcher implements GitlabComposerMetadataFetcherInterface
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
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabRepositorySnapshot {
        $refs = $this->fetchGitRefs($namespace, $repository, $gitlabAccessCredentials);

        return new GitlabRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $namespace,
        string $repository,
        ?string $ref = null,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabPackageMetadata {
        $snapshot = $this->snapshot($namespace, $repository, $gitlabAccessCredentials);
        $refName  = null !== $ref && '' !== trim($ref)
            ? trim($ref)
            : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($namespace, $repository, $gitReference, $snapshot->defaultBranch, $gitlabAccessCredentials);
    }

    public function fetchReference(
        string $namespace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?GitlabAccessCredentials $gitlabAccessCredentials = null
    ): GitlabPackageMetadata {
        return new GitlabPackageMetadata(
            namespace: $namespace,
            repository: $repository,
            reference: $gitReference->name,
            composer: $this->fetchComposerJson(
                $namespace,
                $repository,
                $gitReference->name,
                $gitlabAccessCredentials,
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
        ?GitlabAccessCredentials $gitlabAccessCredentials
    ): array {
        $url = sprintf(
            'https://gitlab.com/api/v4/projects/%s/repository/files/%s/raw?ref=%s',
            rawurlencode($namespace . '/' . $repository),
            rawurlencode('composer.json'),
            rawurlencode($reference),
        );

        $content = $this->fetchComposerJsonContent($url, $this->bearerHttpHeaders($gitlabAccessCredentials));
        if (false === $content && $gitlabAccessCredentials instanceof GitlabAccessCredentials && $gitlabAccessCredentials->hasToken()) {
            $content = $this->fetchComposerJsonContent($url, $this->privateTokenHttpHeaders($gitlabAccessCredentials));
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
    private function fetchGitRefs(string $namespace, string $repository, ?GitlabAccessCredentials $gitlabAccessCredentials): array
    {
        return $this->gitRemoteReferenceReader->lsRemote(
            sprintf('https://gitlab.com/%s/%s.git', $namespace, $repository),
            $this->gitHeaderValues($gitlabAccessCredentials),
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
    private function gitHeaderValues(?GitlabAccessCredentials $gitlabAccessCredentials): array
    {
        if (! $gitlabAccessCredentials instanceof GitlabAccessCredentials || ! $gitlabAccessCredentials->hasToken()) {
            return [];
        }

        $plainToken = $this->storedSecretResolver->resolve($gitlabAccessCredentials->token);
        if ('' === $plainToken) {
            throw new RuntimeException('Stored provider credential could not be decrypted.');
        }

        return [
            GitHttpAuthHeader::basic('oauth2', $plainToken),
            GitHttpAuthHeader::basic('git', $plainToken),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function bearerHttpHeaders(?GitlabAccessCredentials $gitlabAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (
            $gitlabAccessCredentials instanceof GitlabAccessCredentials
            && $gitlabAccessCredentials->hasToken()
        ) {
            $token                    = $this->storedSecretResolver->resolve($gitlabAccessCredentials->token);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function privateTokenHttpHeaders(GitlabAccessCredentials $gitlabAccessCredentials): array
    {
        $token = $this->storedSecretResolver->resolve($gitlabAccessCredentials->token);
        if ('' === $token) {
            throw new RuntimeException('Stored provider credential could not be decrypted.');
        }

        return [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'PRIVATE-TOKEN' => $token,
        ];
    }
}
