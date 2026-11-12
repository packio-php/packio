<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\Github\GithubAccessCredentials;
use RuntimeException;

use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function trim;

final readonly class GithubPackageReadmeExtractor implements PackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private Base64FileContentDecoder $base64FileContentDecoder,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    public function extractGithubReadmeHtml(
        string $owner,
        string $repository,
        string $reference,
        ?GithubAccessCredentials $githubAccessCredentials = null
    ): ?string {
        $readme = $this->fetchGithubReadmeDocument(
            $owner,
            $repository,
            $reference,
            $githubAccessCredentials,
        );
        if (null !== $readme) {
            return $this->readmeExtractorTemplate->render($readme);
        }

        return $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchGithubReadmeCandidate(
                $owner,
                $repository,
                $path,
                $reference,
                $githubAccessCredentials,
            ),
        );
    }

    private function fetchGithubReadmeDocument(
        string $owner,
        string $repository,
        string $reference,
        ?GithubAccessCredentials $githubAccessCredentials
    ): ?string {
        $document = $this->fetchGithubDocument(
            sprintf(
                'https://api.github.com/repos/%s/%s/readme?ref=%s',
                rawurlencode($owner),
                rawurlencode($repository),
                rawurlencode($reference),
            ),
            $githubAccessCredentials,
        );
        if (null !== $document) {
            return $document;
        }

        return $this->fetchGithubDocument(
            sprintf(
                'https://api.github.com/repos/%s/%s/readme',
                rawurlencode($owner),
                rawurlencode($repository),
            ),
            $githubAccessCredentials,
        );
    }

    private function fetchGithubReadmeCandidate(
        string $owner,
        string $repository,
        string $path,
        string $reference,
        ?GithubAccessCredentials $githubAccessCredentials,
    ): ?string {
        return $this->fetchGithubDocument(
            sprintf(
                'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
                rawurlencode($owner),
                rawurlencode($repository),
                rawurlencode($path),
                rawurlencode($reference),
            ),
            $githubAccessCredentials,
        );
    }

    private function fetchGithubDocument(string $url, ?GithubAccessCredentials $githubAccessCredentials): ?string
    {
        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $this->httpHeaders($githubAccessCredentials));
        } catch (UpstreamHttpException) {
            return null;
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            if (
                $response->isNotFound()
                || str_contains($response->body(), '"not found"')
                || str_contains($response->body(), '404: Not Found')
            ) {
                return null;
            }

            throw new RuntimeException(
                sprintf('Remote response from "%s" is not valid JSON.', $url),
            );
        }

        if ($response->isNotFound()) {
            return null;
        }

        $encodedContent = $decoded['content'] ?? null;
        if (! is_string($encodedContent) || '' === $encodedContent) {
            return null;
        }

        $document = $this->base64FileContentDecoder->decode($encodedContent);
        if (false === $document) {
            throw new RuntimeException(
                sprintf(
                    'Remote response from "%s" contains invalid base64 content.',
                    $url,
                ),
            );
        }

        return trim($document);
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?GithubAccessCredentials $githubAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (
            $githubAccessCredentials instanceof GithubAccessCredentials
            && $githubAccessCredentials->hasToken()
        ) {
            $token                    = $this->storedSecretResolver->resolve($githubAccessCredentials->token);
            if ('' === $token) {
                throw new RuntimeException('Stored provider credential could not be decrypted.');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
