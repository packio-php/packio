<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GenericGit;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\RepositoryFilePath;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Git\GitHttpAuthHeader;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use RuntimeException;

use function base64_encode;
use function is_array;
use function json_decode;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function substr;
use function trim;

final readonly class HttpGenericGitComposerMetadataFetcher implements GenericGitComposerMetadataFetcherInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private StoredSecretResolver $storedSecretResolver,
        private GitRemoteReferenceReader $gitRemoteReferenceReader,
    ) {}

    public function snapshot(string $cloneUrl, GenericGitAccessCredentials $genericGitAccessCredentials): GenericGitRepositorySnapshot
    {
        $refs = $this->fetchGitRefs($cloneUrl, $genericGitAccessCredentials);

        return new GenericGitRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $cloneUrl,
        ?string $ref,
        GenericGitAccessCredentials $genericGitAccessCredentials
    ): GenericGitPackageMetadata {
        $snapshot = $this->snapshot($cloneUrl, $genericGitAccessCredentials);
        $refName  = null !== $ref && '' !== trim($ref)
            ? trim($ref)
            : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($cloneUrl, $gitReference, $snapshot->defaultBranch, $genericGitAccessCredentials);
    }

    public function fetchReference(
        string $cloneUrl,
        GitReference $gitReference,
        string $defaultBranch,
        GenericGitAccessCredentials $genericGitAccessCredentials
    ): GenericGitPackageMetadata {
        return new GenericGitPackageMetadata(
            cloneUrl: $cloneUrl,
            reference: $gitReference->name,
            composer: $this->fetchComposerJson($cloneUrl, $gitReference->name, $genericGitAccessCredentials),
            defaultBranch: $defaultBranch,
            commitReference: $gitReference->commit,
            refType: $gitReference->type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComposerJson(string $cloneUrl, string $reference, GenericGitAccessCredentials $genericGitAccessCredentials): array
    {
        $url = $this->constructRawUrl(
            $cloneUrl,
            $reference,
            $genericGitAccessCredentials->composerJsonPath,
        );

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($genericGitAccessCredentials));
        } catch (UpstreamHttpException) {
            throw new RuntimeException(
                sprintf('Failed to fetch remote metadata from "%s".', $url),
            );
        }

        $composer = json_decode($response->body(), true);
        if (! is_array($composer)) {
            throw new RuntimeException(
                sprintf('composer.json from "%s" is not valid JSON.', $url),
            );
        }

        return $composer;
    }

    private function constructRawUrl(string $cloneUrl, string $reference, string $composerJsonPath): string
    {
        $normalized = rtrim($cloneUrl, '/');
        $path       = RepositoryFilePath::urlPath($composerJsonPath);
        if (str_ends_with($normalized, '.git')) {
            return substr($normalized, 0, -4) . '/raw/' . rawurlencode($reference) . '/' . $path;
        }

        return $normalized . '/raw/' . rawurlencode($reference) . '/' . $path;
    }

    /**
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    private function fetchGitRefs(string $cloneUrl, GenericGitAccessCredentials $genericGitAccessCredentials): array
    {
        return $this->gitRemoteReferenceReader->lsRemote(
            $cloneUrl,
            $this->gitHeaderValues($genericGitAccessCredentials),
        );
    }

    /**
     * @return list<string>
     */
    private function gitHeaderValues(GenericGitAccessCredentials $genericGitAccessCredentials): array
    {
        if (! $genericGitAccessCredentials->hasToken()) {
            return [];
        }

        $rawToken = $genericGitAccessCredentials->token ?? '';
        $token    = $this->storedSecretResolver->resolve($rawToken);
        if ('' === $token) {
            throw new RuntimeException('Stored provider credential could not be decrypted.');
        }

        return match ($genericGitAccessCredentials->authScheme) {
            'bearer' => [GitHttpAuthHeader::bearer($token)],
            'basic'  => [GitHttpAuthHeader::basic('git', $token)],
            default  => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(GenericGitAccessCredentials $genericGitAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($genericGitAccessCredentials->hasToken()) {
            $rawToken      = $genericGitAccessCredentials->token ?? '';
            $token         = $this->storedSecretResolver->resolve($rawToken);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $authorization = match ($genericGitAccessCredentials->authScheme) {
                'bearer' => sprintf('Bearer %s', $token),
                'basic'  => sprintf('Basic %s', base64_encode('git:' . $token)),
                default  => '',
            };

            if ('' !== $authorization) {
                $headers['Authorization'] = $authorization;
            }
        }

        return $headers;
    }
}
