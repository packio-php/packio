<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
use RuntimeException;

use function is_array;

final readonly class ProviderApiRequestor
{
    public function __construct(private UpstreamHttpClient $upstreamHttpClient) {}

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: mixed}
     */
    public function getResourcesJson(string $url, array $headers, bool $allowAccessDeniedRetry = false): array
    {
        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $headers);
        } catch (UpstreamHttpException $exception) {
            throw new ProviderCallException(ProviderFailure::Unavailable, $exception);
        }

        $status  = $response->statusCode();
        $failure = match (true) {
            403 === $status && ! $allowAccessDeniedRetry         => ProviderFailure::AccessDenied,
            429 === $status                                      => ProviderFailure::RateLimited,
            $status >= 500                                       => ProviderFailure::Unavailable,
            $status >= 300 && 401 !== $status && 403 !== $status => ProviderFailure::InvalidResponse,
            default                                              => null,
        };
        if (null !== $failure) {
            throw new ProviderCallException($failure, new RuntimeException('Provider HTTP status ' . $status . ': ' . $response->body()));
        }

        $decoded = $response->json();
        if (401 !== $status && ! is_array($decoded)) {
            throw new ProviderCallException(ProviderFailure::InvalidResponse, new RuntimeException('Invalid provider JSON: ' . $response->body()));
        }

        // The fetcher handles 401 so GitLab can retry bearer credentials as a private token.
        return [
            'status' => $status,
            'body'   => $decoded,
        ];
    }
}
