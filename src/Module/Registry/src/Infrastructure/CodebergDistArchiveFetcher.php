<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use RuntimeException;

use function is_string;
use function rawurlencode;
use function sprintf;
use function trim;

final readonly class CodebergDistArchiveFetcher
{
    public function __construct(
        private BrandingConfig $brandingConfig,
        private HttpDistArchiveDownloader $httpDistArchiveDownloader,
    ) {}

    public function fetchArchive(string $owner, string $repository, string $reference, ?string $token = null): DistArchiveStream
    {
        $url = sprintf(
            'https://codeberg.org/api/v1/repos/%s/%s/archive/%s.zip',
            rawurlencode($owner),
            rawurlencode($repository),
            rawurlencode($reference),
        );

        $lastException = null;
        foreach ($this->authSchemes($token) as $authScheme) {
            try {
                return $this->requestArchive($url, $repository, $reference, $token, $authScheme);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new RuntimeException(sprintf('Failed to download archive from "%s".', $url));
    }

    private function requestArchive(
        string $url,
        string $repository,
        string $reference,
        ?string $token,
        ?string $authScheme,
    ): DistArchiveStream {
        $headers = [
            'Accept'     => 'application/octet-stream',
            'User-Agent' => $this->brandingConfig->httpUserAgent(),
        ];

        if (null !== $authScheme && is_string($token) && '' !== trim($token)) {
            $headers['Authorization'] = sprintf('%s %s', $authScheme, trim($token));
        }

        return $this->httpDistArchiveDownloader->downloadZip($url, $headers, $repository . '-' . $reference . '.zip');
    }

    /**
     * @return list<null|string>
     */
    private function authSchemes(?string $token): array
    {
        if (! is_string($token) || '' === trim($token)) {
            return [null];
        }

        return ['Bearer', 'token'];
    }
}
