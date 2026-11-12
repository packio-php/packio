<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Github;

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
use function rawurlencode;
use function sprintf;
use function str_replace;
use function trim;

final readonly class HttpGithubComposerMetadataFetcher implements GithubComposerMetadataFetcherInterface
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
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubRepositorySnapshot {
        $refs = $this->fetchGitRefs($owner, $repository, $githubAccessCredentials);

        return new GithubRepositorySnapshot(
            defaultBranch: $refs['default_branch'],
            tags: $refs['tags'],
            branches: $refs['branches'],
        );
    }

    public function fetch(
        string $owner,
        string $repository,
        ?string $ref = null,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubPackageMetadata {
        $snapshot = $this->snapshot($owner, $repository, $githubAccessCredentials);
        $refName
            = null !== $ref && '' !== trim($ref)
                ? trim($ref)
                : $snapshot->defaultBranch;
        $gitReference = GitReference::resolveByName([...$snapshot->tags, ...$snapshot->branches], $refName);

        return $this->fetchReference($owner, $repository, $gitReference, $snapshot->defaultBranch, $githubAccessCredentials);
    }

    public function fetchReference(
        string $owner,
        string $repository,
        GitReference $gitReference,
        string $defaultBranch,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): GithubPackageMetadata {
        $composer = $this->fetchComposerJson(
            $owner,
            $repository,
            $gitReference->name,
            $githubAccessCredentials,
        );

        return new GithubPackageMetadata(
            owner: $owner,
            repository: $repository,
            reference: $gitReference->name,
            composer: $composer,
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
        ?GithubAccessCredentials $githubAccessCredentials
    ): array {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/composer.json?ref=%s',
            rawurlencode($owner),
            rawurlencode($repository),
            rawurlencode($reference),
        );

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($githubAccessCredentials));
        } catch (UpstreamHttpException) {
            throw new RuntimeException(
                sprintf('Failed to fetch remote metadata from "%s".', $url),
            );
        }

        if ($response->isNotFound()) {
            throw new RuntimeException(
                sprintf('Remote resource "%s" was not found.', $url),
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException(
                sprintf('Remote response from "%s" is not valid JSON.', $url),
            );
        }

        $encodedComposer = $decoded['content'] ?? null;
        if (! is_string($encodedComposer) || '' === $encodedComposer) {
            throw new RuntimeException(
                sprintf(
                    'Remote response from "%s" does not contain composer.json content.',
                    $url,
                ),
            );
        }

        $composerJson = base64_decode(
            str_replace(["\r", "\n"], '', $encodedComposer),
            true,
        );
        if (false === $composerJson) {
            throw new RuntimeException(
                sprintf(
                    'Remote response from "%s" contains invalid base64 content.',
                    $url,
                ),
            );
        }

        $composer = json_decode($composerJson, true);
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
    private function fetchGitRefs(string $owner, string $repository, ?GithubAccessCredentials $githubAccessCredentials): array
    {
        $token = '';
        if ($githubAccessCredentials instanceof GithubAccessCredentials && $githubAccessCredentials->hasToken()) {
            $rawToken = $githubAccessCredentials->token ?? '';
            $token    = $this->storedSecretResolver->resolve($rawToken);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
        }

        return $this->gitRemoteReferenceReader->lsRemote(
            sprintf('https://github.com/%s/%s.git', $owner, $repository),
            '' !== $token ? [GitHttpAuthHeader::basic('x-access-token', $token)] : [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?GithubAccessCredentials $githubAccessCredentials): array
    {
        $headers = [
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
            'Accept'     => 'application/vnd.github+json',
        ];

        if (
            $githubAccessCredentials instanceof GithubAccessCredentials
            && $githubAccessCredentials->hasToken()
        ) {
            $rawToken                 = $githubAccessCredentials->token ?? '';
            $token                    = $this->storedSecretResolver->resolve($rawToken);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
