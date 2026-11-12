<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Gitlab;

use Common\Branding\BrandingConfig;
use Common\Logging\SafeExceptionContext;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use Psr\Log\LoggerInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

final readonly class HttpGitlabProviderResourceFetcher implements GitlabProviderResourceFetcherInterface
{
    private const string PROJECTS_URL = 'https://gitlab.com/api/v4/projects?membership=true&per_page=100';

    public function __construct(
        private BrandingConfig $brandingConfig,
        private ProviderApiRequestor $providerApiRequestor,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(?string $token = null): array
    {
        $decoded = $this->fetchProjects($token);

        return $this->mapProjects($decoded);
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, '[GitLab Resource Fetcher] ' . SafeExceptionContext::redact($message));
        }
    }

    private function fetchProjects(?string $token): mixed
    {
        $hasToken = null !== $token && '' !== trim($token);
        $response = $this->fetchUrl(self::PROJECTS_URL, $this->oauthHeaders($token));

        if (401 === $response['status'] && $hasToken) {
            $this->log('debug', 'OAuth bearer request was unauthorized; retrying as private token.');
            $response = $this->fetchUrl(self::PROJECTS_URL, $this->privateTokenHeaders($token));
        }

        if (401 === $response['status']) {
            if (! $hasToken) {
                return null;
            }

            throw new ProviderAuthenticationException($this->errorMessage($response['body']));
        }

        return $response['body'];
    }

    /**
     * @return list<ProviderResourceData>
     */
    private function mapProjects(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static function(mixed $project): ?ProviderResourceData {
                        if (! is_array($project)) {
                            return null;
                        }

                        $pathWithNamespace = trim((string) ($project['path_with_namespace'] ?? ''));
                        $webUrl            = trim((string) ($project['web_url'] ?? ''));
                        if ('' === $pathWithNamespace || '' === $webUrl) {
                            return null;
                        }

                        return new ProviderResourceData(
                            id: $pathWithNamespace,
                            name: $pathWithNamespace,
                            type: 'gitlab',
                            url: $webUrl,
                            defaultBranch: trim((string) ($project['default_branch'] ?? '')),
                            description: isset($project['description']) && is_string($project['description'])
                                ? trim($project['description'])
                                : null,
                            private: 'public' !== (string) ($project['visibility'] ?? 'private'),
                        );
                    },
                    $decoded,
                ),
            ),
        );
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: mixed}
     */
    private function fetchUrl(string $url, array $headers): array
    {
        $this->log('debug', sprintf('Fetching URL: %s', $url));

        $response = $this->providerApiRequestor->getResourcesJson($url, $headers);
        if (0 === $response['status']) {
            $this->log('warning', sprintf('HTTP request failed for URL: %s', $url));

            return [
                'status' => 0,
                'body'   => null,
            ];
        }

        $decoded = $response['body'];

        if (is_array($decoded) && $this->isErrorResponse($decoded)) {
            $this->log('warning', sprintf('API error for URL %s: %s', $url, $this->errorMessage($decoded)));

            return [
                'status' => $response['status'],
                'body'   => $decoded,
            ];
        }

        if (! is_array($decoded)) {
            $this->log('warning', sprintf('Non-array response from URL: %s', $url));

            return [
                'status' => $response['status'],
                'body'   => null,
            ];
        }

        $this->log('debug', sprintf('Success for URL: %s, returned %d items', $url, count($decoded)));

        return [
            'status' => $response['status'],
            'body'   => $decoded,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function oauthHeaders(?string $token): array
    {
        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (null !== $token && '' !== trim($token)) {
            $headers['Authorization'] = sprintf('Bearer %s', trim($token));
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function privateTokenHeaders(string $token): array
    {
        return [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'PRIVATE-TOKEN' => trim($token),
        ];
    }

    /**
     * @param array<int|string, mixed> $decoded
     */
    private function isErrorResponse(array $decoded): bool
    {
        return isset($decoded['message']) || isset($decoded['error']) || isset($decoded['error_description']);
    }

    private function errorMessage(mixed $decoded): string
    {
        if (! is_array($decoded)) {
            return 'GitLab authentication failed.';
        }

        foreach (['message', 'error_description', 'error'] as $key) {
            if (is_string($decoded[$key] ?? null) && '' !== trim($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return 'GitLab authentication failed.';
    }
}
