<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use RuntimeException;

use function sprintf;

final readonly class HttpDistArchiveDownloader
{
    public function __construct(private UpstreamHttpClient $upstreamHttpClient) {}

    /**
     * @param array<string, string> $headers
     */
    public function downloadZip(
        string $url,
        array $headers,
        string $fileName,
        int $timeout = 60,
    ): DistArchiveStream {
        try {
            $response = $this->upstreamHttpClient->requestStream('GET', $url, $headers, timeout: $timeout);
        } catch (UpstreamHttpException) {
            throw new RuntimeException(sprintf('Failed to download archive from "%s".', $url));
        }

        HttpArchiveResponseGuard::assertSuccessful($url, [sprintf('HTTP/1.1 %d', $response->statusCode())]);

        return new DistArchiveStream(
            body: $response->body(),
            contentType: $response->contentType(),
            contentLength: $response->contentLength(),
            fileName: $fileName,
        );
    }
}
