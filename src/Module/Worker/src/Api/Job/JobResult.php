<?php

declare(strict_types=1);

namespace Packio\Worker\Api\Job;

final readonly class JobResult
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private bool $success,
        private ?string $errorMessage = null,
        private array $data = [],
        private bool $shouldRetry = true
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data = []): self
    {
        return new self(true, null, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function failure(string $message, array $data = [], bool $shouldRetry = true): self
    {
        return new self(false, $message, $data, $shouldRetry);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function shouldRetry(): bool
    {
        return $this->shouldRetry;
    }
}
