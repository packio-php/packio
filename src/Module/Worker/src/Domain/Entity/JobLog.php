<?php

declare(strict_types=1);

namespace Packio\Worker\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class JobLog implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;

    public const LEVEL_DEBUG   = 'debug';
    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';

    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly string $jobId = '',
        private readonly string $level = self::LEVEL_INFO,
        private readonly string $message = '',
        private readonly array $context = [],
    ) {}

    /** @param array<string, mixed> $context */
    public static function record(string $jobId, string $level, string $message, array $context = []): self
    {
        return new self(jobId: $jobId, level: $level, message: $message, context: $context);
    }

    public function jobId(): string
    {
        return $this->jobId;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
