<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use RuntimeException;

use function rawurlencode;
use function rtrim;
use function sprintf;
use function trim;

final readonly class GitlabSelfHostedDistArchiveFetcher
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private HttpDistArchiveDownloader $httpDistArchiveDownloader,
    ) {}

    public function fetchArchive(
        string $baseUrl,
        string $namespace,
        string $repository,
        string $reference,
        ?string $token = null,
        string $authStrategy = '',
    ): DistArchiveStream {
        if ('' === trim($baseUrl)) {
            throw new RuntimeException('GitLab self-hosted base URL is required to fetch release archives.');
        }

        $url = sprintf(
            '%s/api/v4/projects/%s/repository/archive.zip?sha=%s',
            rtrim($baseUrl, '/'),
            rawurlencode($namespace . '/' . $repository),
            rawurlencode($reference),
        );

        return $this->httpDistArchiveDownloader->downloadZip(
            $url,
            $this->headers($token, $authStrategy),
            $repository . '-' . $reference . '.zip',
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $token, string $authStrategy): array
    {
        $headers = [
            'Accept'     => 'application/zip',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (null === $token || '' === trim($token)) {
            return $headers;
        }

        $token = trim($token);
        if ('oauth2' === $authStrategy) {
            $headers['Authorization'] = 'Bearer ' . $token;

            return $headers;
        }

        $headers['PRIVATE-TOKEN'] = $token;

        return $headers;
    }
}
