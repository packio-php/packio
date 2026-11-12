<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Psr\Http\Message\StreamInterface;

use function is_numeric;
use function strtolower;

final readonly class UpstreamHttpStreamResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(private int $statusCode, private array $headers, private StreamInterface $stream) {}

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): StreamInterface
    {
        return $this->stream;
    }

    public function headerLine(string $name): string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $headerName => $values) {
            if (strtolower($headerName) === $needle) {
                return $values[0] ?? '';
            }
        }

        return '';
    }

    public function contentLength(): ?int
    {
        $contentLength = $this->headerLine('Content-Length');
        if ('' === $contentLength || ! is_numeric($contentLength)) {
            return null;
        }

        return (int) $contentLength;
    }

    public function contentType(string $default = 'application/zip'): string
    {
        $contentType = $this->headerLine('Content-Type');
        if ('' === $contentType) {
            return $default;
        }

        return $contentType;
    }
}
