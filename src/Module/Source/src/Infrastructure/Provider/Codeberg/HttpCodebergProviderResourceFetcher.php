<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Codeberg;

use Common\Branding\BrandingConfig;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use RuntimeException;

use function count;
use function http_build_query;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function trim;

final readonly class HttpCodebergProviderResourceFetcher implements CodebergProviderResourceFetcherInterface
{
    private const int PAGE_LIMIT = 50;

    public function __construct(
        private BrandingConfig $brandingConfig,
        private ProviderApiRequestor $providerApiRequestor,
    ) {}

    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $accessToken = null): array
    {
        if (null === $accessToken || '' === trim($accessToken)) {
            return [];
        }

        $resources = [];
        $page      = 1;

        do {
            $url = 'https://codeberg.org/api/v1/user/repos?' . http_build_query([
                'page'  => $page,
                'limit' => self::PAGE_LIMIT,
            ]);
            $payload = $this->request($url, $accessToken, true);
            if (! is_array($payload)) {
                break;
            }

            foreach ($payload as $repository) {
                if (! is_array($repository)) {
                    continue;
                }

                $resource = $this->mapRepository($repository);
                if ($resource instanceof ProviderResourceData) {
                    $resources[] = $resource;
                }
            }

            ++$page;
        } while (count($payload) >= self::PAGE_LIMIT);

        return $resources;
    }

    /**
     * @param array<string, mixed> $repository
     */
    private function mapRepository(array $repository): ?ProviderResourceData
    {
        $fullName = $repository['full_name'] ?? null;
        if (! is_string($fullName) || '' === trim($fullName)) {
            return null;
        }

        $htmlUrl = $repository['html_url'] ?? null;

        return new ProviderResourceData(
            id: trim($fullName),
            name: trim($fullName),
            type: 'codeberg',
            url: is_string($htmlUrl) && '' !== trim($htmlUrl)
                ? trim($htmlUrl)
                : sprintf('https://codeberg.org/%s', trim($fullName)),
            defaultBranch: is_string($repository['default_branch'] ?? null) && '' !== trim($repository['default_branch'])
                ? trim($repository['default_branch'])
                : 'main',
            description: is_string($repository['description'] ?? null) && '' !== trim($repository['description'])
                ? trim($repository['description'])
                : null,
            private: is_bool($repository['private'] ?? null) && $repository['private'],
        );
    }

    private function request(string $url, string $accessToken, bool $retryAsManualToken): mixed
    {
        $response = $this->fetchUrl($url, $this->headers($accessToken, false));

        if ((401 === $response['status'] || 403 === $response['status']) && $retryAsManualToken) {
            $response = $this->fetchUrl($url, $this->headers($accessToken, true));
        }

        if (403 === $response['status']) {
            throw new ProviderCallException(ProviderFailure::AccessDenied, new RuntimeException($this->errorMessage($response['body'])));
        }

        if (401 === $response['status']) {
            throw new ProviderAuthenticationException($this->errorMessage($response['body']));
        }

        if ($response['status'] >= 400) {
            throw new RuntimeException(sprintf('Codeberg repositories request failed with HTTP %d: %s', $response['status'], $this->errorMessage($response['body'])));
        }

        return $response['body'];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: mixed}
     */
    private function fetchUrl(string $url, array $headers): array
    {
        return $this->providerApiRequestor->getResourcesJson($url, $headers, allowAccessDeniedRetry: true);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accessToken, bool $manualToken): array
    {
        return [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'Authorization' => sprintf('%s %s', $manualToken ? 'token' : 'Bearer', trim($accessToken)),
        ];
    }

    private function errorMessage(mixed $decoded): string
    {
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? null;
            if (is_string($message) && '' !== trim($message)) {
                return trim($message);
            }
        }

        return 'Codeberg rejected the repository request.';
    }
}
