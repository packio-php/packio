<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Publication\Infrastructure\GenericGit\GenericGitAccessCredentials;
use RuntimeException;

use function base64_encode;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function substr;
use function trim;

final readonly class GenericGitPackageReadmeExtractor implements GenericGitPackageReadmeExtractorInterface
{
    public function __construct(
        private UpstreamHttpClient $upstreamHttpClient,
        private BrandingConfig $brandingConfig,
        private ReadmeExtractorTemplate $readmeExtractorTemplate,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    public function extractGenericGitReadmeHtml(
        string $cloneUrl,
        string $reference,
        ?GenericGitAccessCredentials $genericGitAccessCredentials = null
    ): ?string {
        return $this->readmeExtractorTemplate->extract(
            fn (string $path): ?string => $this->fetchGenericGitReadmeCandidate(
                $cloneUrl,
                $path,
                $reference,
                $genericGitAccessCredentials,
            ),
        );
    }

    private function fetchGenericGitReadmeCandidate(
        string $cloneUrl,
        string $path,
        string $reference,
        ?GenericGitAccessCredentials $genericGitAccessCredentials,
    ): ?string {
        try {
            $response = $this->upstreamHttpClient->request(
                'GET',
                $this->constructRawUrl($cloneUrl, $reference, $path),
                $this->httpHeaders($genericGitAccessCredentials),
            );
        } catch (UpstreamHttpException) {
            return null;
        }

        if ($response->statusCode() >= 400 || '' === trim($response->body())) {
            return null;
        }

        return trim($response->body());
    }

    private function constructRawUrl(string $cloneUrl, string $reference, string $path): string
    {
        $normalized = rtrim($cloneUrl, '/');
        $path       = trim($path, "/ \t\n\r\0\x0B");
        if (str_ends_with($normalized, '.git')) {
            return substr($normalized, 0, -4) . '/raw/' . $reference . '/' . $path;
        }

        return $normalized . '/raw/' . $reference . '/' . $path;
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?GenericGitAccessCredentials $genericGitAccessCredentials): array
    {
        $headers = [
            'Accept'     => 'text/plain',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if ($genericGitAccessCredentials instanceof GenericGitAccessCredentials && $genericGitAccessCredentials->hasToken()) {
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
