<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use RuntimeException;

use function rawurlencode;
use function sprintf;
use function trim;

final readonly class GitlabSelfHostedPackageReadmeExtractor implements GitlabSelfHostedPackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    public function extractGitlabSelfHostedReadmeHtml(
        string $namespace,
        string $repository,
        string $reference,
        ?GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials = null,
    ): ?string {
        if (! $gitlabSelfHostedAccessCredentials instanceof GitlabSelfHostedAccessCredentials) {
            return null;
        }

        return $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchGitlabSelfHostedReadmeCandidate(
                $namespace,
                $repository,
                $path,
                $reference,
                $gitlabSelfHostedAccessCredentials,
            ),
        );
    }

    private function fetchGitlabSelfHostedReadmeCandidate(
        string $namespace,
        string $repository,
        string $path,
        string $reference,
        GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials,
    ): ?string {
        $url = sprintf(
            '%s/projects/%s/repository/files/%s/raw?ref=%s',
            $gitlabSelfHostedAccessCredentials->apiBaseUrl(),
            rawurlencode($namespace . '/' . $repository),
            rawurlencode($path),
            rawurlencode($reference),
        );

        foreach ($this->httpHeaderSets($gitlabSelfHostedAccessCredentials) as $headers) {
            try {
                $response = $this->upstreamHttpClient->request('GET', $url, $headers);
            } catch (UpstreamHttpException) {
                continue;
            }

            if ($response->statusCode() >= 400) {
                continue;
            }

            if ('' === trim($response->body())) {
                continue;
            }

            return trim($response->body());
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function httpHeaderSets(GitlabSelfHostedAccessCredentials $gitlabSelfHostedAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'text/plain',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (! $gitlabSelfHostedAccessCredentials->hasToken()) {
            return [$headers];
        }

        $token = $this->resolveToken($gitlabSelfHostedAccessCredentials->token);

        return [
            [
                ...$headers,
                'Authorization' => 'Bearer ' . $token,
            ],
            [
                ...$headers,
                'PRIVATE-TOKEN' => $token,
            ],
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
