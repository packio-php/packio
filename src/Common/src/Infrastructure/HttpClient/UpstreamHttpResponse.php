<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use function is_array;
use function json_decode;

final readonly class UpstreamHttpResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(private int $statusCode, private array $headers, private string $body) {}

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): mixed
    {
        if ('' === $this->body) {
            return null;
        }

        return json_decode($this->body, true);
    }

    /**
     * @return null|array<array-key, mixed>
     */
    public function jsonArray(): ?array
    {
        $decoded = $this->json();

        return is_array($decoded) ? $decoded : null;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isNotFound(): bool
    {
        return 404 === $this->statusCode;
    }

    public function isAuthFailure(): bool
    {
        return 401 === $this->statusCode || 403 === $this->statusCode;
    }
}
