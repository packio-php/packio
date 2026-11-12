<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Bitbucket;

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

final readonly class HttpBitbucketComposerMetadataFetcher implements BitbucketComposerMetadataFetcherInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private StoredSecretResolver $storedSecretResolver,
        private GitRemoteReferenceReader $gitRemoteReferenceReader,
    ) {}

    public function snapshot(
        string $workspace,
        string $repository,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): BitbucketRepositorySnapshot {
        $refs = $this->fetchGitRefs($workspace, $repository, $bitbucketAccessCredentials);

        return new BitbucketRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $workspace,
        string $repository,
        ?string $ref = null,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null,
    ): BitbucketPackageMetadata {
        $snapshot = $this->snapshot($workspace, $repository, $bitbucketAccessCredentials);
        $refName  = null !== $ref && '' !== trim($ref)
            ? trim($ref)
            : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($workspace, $repository, $gitReference, $snapshot->defaultBranch, $bitbucketAccessCredentials);
    }

    public function fetchReference(
        string $workspace,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials = null
    ): BitbucketPackageMetadata {
        return new BitbucketPackageMetadata(
            workspace: $workspace,
            repository: $repository,
            reference: $gitReference->name,
            composer: $this->fetchComposerJson($workspace, $repository, $gitReference->name, $bitbucketAccessCredentials),
            defaultBranch: $defaultBranch,
            commitReference: $gitReference->commit,
            refType: $gitReference->type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComposerJson(
        string $workspace,
        string $repository,
        string $reference,
        ?BitbucketAccessCredentials $bitbucketAccessCredentials,
    ): array {
        $url = sprintf(
            'https://api.bitbucket.org/2.0/repositories/%s/%s/src/%s/%s',
            rawurlencode($workspace),
            rawurlencode($repository),
            rawurlencode($reference),
            rawurlencode('composer.json'),
        );

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($bitbucketAccessCredentials));
        } catch (UpstreamHttpException) {
            throw new RuntimeException(sprintf('Failed to fetch remote metadata from "%s".', $url));
        }

        $composer = json_decode($response->body(), true);
        if (! is_array($composer)) {
            throw new RuntimeException(sprintf('composer.json from "%s" is not valid JSON.', $url));
        }

        return $composer;
    }

    /**
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    private function fetchGitRefs(string $workspace, string $repository, ?BitbucketAccessCredentials $bitbucketAccessCredentials): array
    {
        $token = $this->plainToken($bitbucketAccessCredentials);

        return $this->gitRemoteReferenceReader->lsRemote(
            sprintf('https://bitbucket.org/%s/%s.git', $workspace, $repository),
            '' !== $token ? [GitHttpAuthHeader::basic('x-token-auth', $token)] : [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?BitbucketAccessCredentials $bitbucketAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        $token = $this->plainToken($bitbucketAccessCredentials);
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function plainToken(?BitbucketAccessCredentials $bitbucketAccessCredentials): string
    {
        if (! $bitbucketAccessCredentials instanceof BitbucketAccessCredentials || ! $bitbucketAccessCredentials->hasToken()) {
            return '';
        }

        $rawToken = $bitbucketAccessCredentials->token ?? '';

        return trim($this->storedSecretResolver->resolve($rawToken));
    }
}
