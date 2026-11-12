<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\GitlabSelfHosted;

use Common\Branding\BrandingConfig;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function rtrim;
use function sprintf;
use function trim;

final readonly class HttpGitlabSelfHostedProviderResourceFetcher implements GitlabSelfHostedProviderResourceFetcherInterface
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private ProviderApiRequestor $providerApiRequestor,
    ) {}

    /**
     * @return list<ProviderResourceData>
     */
    public function fetchResources(string $baseUrl, ?string $token = null): array
    {
        $apiUrl = sprintf(
            '%s/api/v4/projects?membership=true&simple=true&per_page=100&order_by=last_activity_at&sort=desc',
            rtrim($baseUrl, '/'),
        );

        $response = $this->providerApiRequestor->getResourcesJson($apiUrl, $this->httpHeaders($token));
        if (401 === $response['status']) {
            throw new ProviderAuthenticationException('Provider authorization is invalid or expired. Please reconnect.');
        }

        $decoded  = $response['body'];
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
                            type: 'gitlab_self_hosted',
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
     * @return array<string, string>
     */
    private function httpHeaders(?string $token): array
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
}
