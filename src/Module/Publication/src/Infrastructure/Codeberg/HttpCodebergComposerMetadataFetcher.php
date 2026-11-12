<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Codeberg;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Git\GitHttpAuthHeader;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use RuntimeException;

use function base64_decode;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class HttpCodebergComposerMetadataFetcher implements CodebergComposerMetadataFetcherInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private StoredSecretResolver $storedSecretResolver,
        private GitRemoteReferenceReader $gitRemoteReferenceReader,
    ) {}

    public function snapshot(
        string $owner,
        string $repository,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): CodebergRepositorySnapshot {
        $refs = $this->fetchGitRefs($owner, $repository, $codebergAccessCredentials);

        return new CodebergRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $owner,
        string $repository,
        ?string $ref = null,
        ?CodebergAccessCredentials $codebergAccessCredentials = null,
    ): CodebergPackageMetadata {
        $snapshot = $this->snapshot($owner, $repository, $codebergAccessCredentials);
        $refName  = null !== $ref && '' !== trim($ref)
            ? trim($ref)
            : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($owner, $repository, $gitReference, $snapshot->defaultBranch, $codebergAccessCredentials);
    }

    public function fetchReference(
        string $owner,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?CodebergAccessCredentials $codebergAccessCredentials = null
    ): CodebergPackageMetadata {
        return new CodebergPackageMetadata(
            owner: $owner,
            repository: $repository,
            reference: $gitReference->name,
            composer: $this->fetchComposerJson($owner, $repository, $gitReference->name, $codebergAccessCredentials),
            defaultBranch: $defaultBranch,
            commitReference: $gitReference->commit,
            refType: $gitReference->type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComposerJson(
        string $owner,
        string $repository,
        string $reference,
        ?CodebergAccessCredentials $codebergAccessCredentials
    ): array {
        $url = sprintf(
            'https://codeberg.org/api/v1/repos/%s/%s/contents/%s?ref=%s',
            rawurlencode($owner),
            rawurlencode($repository),
            rawurlencode('composer.json'),
            rawurlencode($reference),
        );

        $content  = $this->fetchContentsFile($url, $codebergAccessCredentials);
        $composer = json_decode($content, true);
        if (! is_array($composer)) {
            throw new RuntimeException(sprintf('composer.json from "%s" is not valid JSON.', $url));
        }

        return $composer;
    }

    private function fetchContentsFile(string $url, ?CodebergAccessCredentials $codebergAccessCredentials): string
    {
        $lastError = '';
        foreach ([false, true] as $manualToken) {
            $response = $this->apiRequest($url, $codebergAccessCredentials, $manualToken);
            if ($response['status'] >= 400) {
                $lastError = $this->apiErrorMessage($response['body']);

                continue;
            }

            $content = $this->decodeContentsResponse($response['body']);
            if (null !== $content) {
                return $content;
            }

            $lastError = $this->apiErrorMessage($response['body']);
        }

        throw new RuntimeException(sprintf(
            'Failed to fetch remote metadata from "%s"%s.',
            $url,
            '' !== $lastError ? ': ' . $lastError : '',
        ));
    }

    /**
     * @return array{status: int, body: string}
     */
    private function apiRequest(string $url, ?CodebergAccessCredentials $codebergAccessCredentials, bool $manualToken): array
    {
        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($codebergAccessCredentials, $manualToken));
        } catch (UpstreamHttpException) {
            return [
                'status' => 0,
                'body'   => '',
            ];
        }

        return [
            'status' => $response->statusCode(),
            'body'   => $response->body(),
        ];
    }

    private function decodeContentsResponse(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        $encodedContent = $decoded['content'] ?? null;
        if (! is_string($encodedContent) || '' === trim($encodedContent)) {
            return null;
        }

        $normalizedContent = preg_replace('/\s+/', '', $encodedContent);
        if (! is_string($normalizedContent) || '' === $normalizedContent) {
            return null;
        }

        $content = base64_decode($normalizedContent, true);

        return false === $content ? null : $content;
    }

    private function apiErrorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && is_string($decoded['message'] ?? null) && '' !== trim($decoded['message'])) {
            return trim($decoded['message']);
        }

        return '' !== trim($body) ? trim($body) : 'Codeberg API did not return file content.';
    }

    /**
     * @return array{default_branch: string, tags: list<GitReference>, branches: list<GitReference>}
     */
    private function fetchGitRefs(string $owner, string $repository, ?CodebergAccessCredentials $codebergAccessCredentials): array
    {
        $remote = sprintf('https://codeberg.org/%s/%s.git', $owner, $repository);
        $token  = $this->plainToken($codebergAccessCredentials);

        return $this->gitRemoteReferenceReader->lsRemote(
            $remote,
            '' !== $token ? [GitHttpAuthHeader::bearer($token), GitHttpAuthHeader::token($token)] : [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?CodebergAccessCredentials $codebergAccessCredentials, bool $manualToken): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        $token = $this->plainToken($codebergAccessCredentials);
        if ('' !== $token) {
            $headers['Authorization'] = ($manualToken ? 'token ' : 'Bearer ') . $token;
        }

        return $headers;
    }

    private function plainToken(?CodebergAccessCredentials $codebergAccessCredentials): string
    {
        if (! $codebergAccessCredentials instanceof CodebergAccessCredentials || ! $codebergAccessCredentials->hasToken()) {
            return '';
        }

        $rawToken = $codebergAccessCredentials->token ?? '';

        return trim($this->storedSecretResolver->resolve($rawToken));
    }
}
