<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Bitbucket;

use Common\Branding\BrandingConfig;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use RuntimeException;

use function is_array;
use function is_bool;
use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class HttpBitbucketProviderResourceFetcher implements BitbucketProviderResourceFetcherInterface
{
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
        $seen      = [];

        foreach ($this->fetchWorkspaceSlugs($accessToken) as $workspaceSlug) {
            $url = sprintf('https://api.bitbucket.org/2.0/repositories/%s?role=member&pagelen=100', rawurlencode($workspaceSlug));

            while ('' !== $url) {
                $payload = $this->request($url, $accessToken);
                foreach ($this->mapRepositories($payload) as $resource) {
                    if (isset($seen[$resource->id])) {
                        continue;
                    }

                    $seen[$resource->id] = true;
                    $resources[]         = $resource;
                }

                $next = $payload['next'] ?? null;
                $url  = is_string($next) ? $next : '';
            }
        }

        return $resources;
    }

    /**
     * @return list<string>
     */
    private function fetchWorkspaceSlugs(string $accessToken): array
    {
        $workspaceSlugs = [];
        $url            = 'https://api.bitbucket.org/2.0/user/workspaces?pagelen=100';

        while ('' !== $url) {
            $payload = $this->request($url, $accessToken);
            foreach ((array) ($payload['values'] ?? []) as $workspaceAccess) {
                if (! is_array($workspaceAccess)) {
                    continue;
                }

                $workspaceSlug = $workspaceAccess['workspace']['slug'] ?? null;
                if (is_string($workspaceSlug) && '' !== trim($workspaceSlug)) {
                    $workspaceSlugs[] = trim($workspaceSlug);
                }
            }

            $next = $payload['next'] ?? null;
            $url  = is_string($next) ? $next : '';
        }

        return $workspaceSlugs;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<ProviderResourceData>
     */
    private function mapRepositories(array $payload): array
    {
        $resources = [];

        foreach ((array) ($payload['values'] ?? []) as $repository) {
            if (! is_array($repository)) {
                continue;
            }

            $fullName = $repository['full_name'] ?? null;
            if (! is_string($fullName)) {
                continue;
            }

            if ('' === trim($fullName)) {
                continue;
            }

            $htmlUrl    = $repository['links']['html']['href'] ?? null;
            $mainBranch = $repository['mainbranch']['name'] ?? null;

            $resources[] = new ProviderResourceData(
                id: $fullName,
                name: $fullName,
                type: 'git',
                url: is_string($htmlUrl) && '' !== $htmlUrl
                    ? $htmlUrl
                    : sprintf('https://bitbucket.org/%s', $fullName),
                defaultBranch: is_string($mainBranch) && '' !== trim($mainBranch)
                    ? trim($mainBranch)
                    : 'main',
                private: is_bool($repository['is_private'] ?? null) && $repository['is_private'],
            );
        }

        return $resources;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $url, string $accessToken): array
    {
        $response = $this->providerApiRequestor->getResourcesJson($url, [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'Authorization' => 'Bearer ' . trim($accessToken),
        ]);

        if (401 === $response['status']) {
            throw new ProviderAuthenticationException('Provider authorization is invalid or expired. Please reconnect.');
        }

        $decoded = $response['body'];
        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Bitbucket response from "%s" is not valid JSON.', $url));
        }

        if (403 === $response['status']) {
            throw new ProviderAuthenticationException($this->errorMessage($decoded));
        }

        if ($response['status'] >= 400) {
            throw new RuntimeException(sprintf('Bitbucket repositories request failed with HTTP %d: %s', $response['status'], $this->errorMessage($decoded)));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function errorMessage(array $decoded): string
    {
        $message = $decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? null;

        return is_string($message) && '' !== trim($message)
            ? trim($message)
            : 'Bitbucket rejected the repository request.';
    }
}
