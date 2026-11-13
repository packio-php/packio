<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Github;

use Common\Branding\BrandingConfig;
use Common\Logging\SafeExceptionContext;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
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

final readonly class HttpGithubProviderResourceFetcher implements GithubProviderResourceFetcherInterface
{
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
        $maskedToken = null !== $token && '' !== trim($token) ? '[REDACTED]' : 'null';
        $this->log('info', sprintf('Starting fetchResources with token: %s', $maskedToken));

        $personal = $this->fetchUserRepos($token);
        $orgRepos = $this->fetchOrgRepos($token);

        $this->log('info', sprintf(
            'Fetched %d personal repos and %d org repos',
            count($personal),
            count($orgRepos),
        ));

        $seen = [];
        $all  = [];
        foreach ([...$personal, ...$orgRepos] as $repo) {
            if (! isset($seen[$repo->id])) {
                $seen[$repo->id] = true;
                $all[]           = $repo;
            }
        }

        $this->log('info', sprintf('Returning %d unique resources', count($all)));

        return $all;
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, '[GitHub Resource Fetcher] ' . SafeExceptionContext::redact($message));
        }
    }

    /**
     * @return list<ProviderResourceData>
     */
    private function fetchUserRepos(?string $token): array
    {
        $data = $this->fetchUrl(
            'https://api.github.com/user/repos?per_page=100&sort=updated&type=all',
            $token,
        );

        return $this->mapRepositories($data);
    }

    /**
     * @return list<ProviderResourceData>
     */
    private function fetchOrgRepos(?string $token): array
    {
        $orgs = $this->fetchUrlUnlessDenied(
            'https://api.github.com/user/orgs?per_page=100',
            $token,
        );

        if (! is_array($orgs)) {
            return [];
        }

        $all = [];
        foreach ($orgs as $org) {
            if (! is_array($org)) {
                continue;
            }

            if (! isset($org['login'])) {
                continue;
            }
            $login = trim((string) $org['login']);
            if ('' === $login) {
                continue;
            }

            $repos = $this->fetchUrlUnlessDenied(
                sprintf('https://api.github.com/orgs/%s/repos?per_page=100&sort=updated&type=all', $login),
                $token,
            );

            $all = [...$all, ...$this->mapRepositories($repos)];
        }

        return $all;
    }

    /**
     * @return list<ProviderResourceData>
     */
    private function mapRepositories(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static function(mixed $repository): ?ProviderResourceData {
                        if (! is_array($repository)) {
                            return null;
                        }

                        $fullName = trim((string) ($repository['full_name'] ?? ''));
                        $htmlUrl  = trim((string) ($repository['html_url'] ?? ''));
                        if ('' === $fullName || '' === $htmlUrl) {
                            return null;
                        }

                        return new ProviderResourceData(
                            id: $fullName,
                            name: $fullName,
                            type: 'github',
                            url: $htmlUrl,
                            defaultBranch: trim((string) ($repository['default_branch'] ?? 'main')),
                            description: isset($repository['description']) && is_string($repository['description'])
                                ? trim($repository['description'])
                                : null,
                            private: (bool) ($repository['private'] ?? false),
                        );
                    },
                    $decoded,
                ),
            ),
        );
    }

    /**
     * Organization endpoints can be forbidden (no `read:org` scope, SSO-protected organization)
     * while the user's own repository list works, so a 403 here skips the organization.
     */
    private function fetchUrlUnlessDenied(string $url, ?string $token): mixed
    {
        try {
            return $this->fetchUrl($url, $token);
        } catch (ProviderCallException $providerCallException) {
            if (ProviderFailure::AccessDenied !== $providerCallException->failure) {
                throw $providerCallException;
            }

            $this->log('warning', sprintf('Access denied for URL %s, skipping organization repositories', $url));

            return null;
        }
    }

    private function fetchUrl(string $url, ?string $token): mixed
    {
        $maskedToken = null !== $token && '' !== trim($token) ? '[REDACTED]' : 'null';
        $this->log('debug', sprintf('Fetching URL: %s (token: %s)', $url, $maskedToken));

        $response = $this->providerApiRequestor->getResourcesJson($url, $this->httpHeaders($token));
        if (0 === $response['status']) {
            $this->log('warning', sprintf('HTTP request failed for URL: %s', $url));

            return null;
        }

        if (401 === $response['status']) {
            throw new ProviderAuthenticationException('Provider authorization is invalid or expired. Please reconnect.');
        }

        $decoded = $response['body'];

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $this->log('warning', sprintf('API error for URL %s: %s', $url, $decoded['message']));

            return null;
        }

        if (! is_array($decoded)) {
            $this->log('warning', sprintf('Non-array response from URL: %s', $url));

            return null;
        }

        $this->log('debug', sprintf('Success for URL: %s, returned %d items', $url, count($decoded)));

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(?string $token): array
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (null !== $token && '' !== trim($token)) {
            $headers['Authorization'] = sprintf('Bearer %s', trim($token));
        }

        return $headers;
    }
}
