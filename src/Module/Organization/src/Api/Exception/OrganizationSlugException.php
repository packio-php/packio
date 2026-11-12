<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Exception;

use RuntimeException;

final class OrganizationSlugException extends RuntimeException implements OrganizationExceptionInterface
{
    private const string REQUIRED      = 'required';
    private const string IMMUTABLE     = 'immutable';
    private const string ALREADY_TAKEN = 'already_taken';
    private const string RESERVED      = 'reserved';

    private function __construct(private readonly string $reason)
    {
        parent::__construct($reason);
    }

    public static function required(): self
    {
        return new self(self::REQUIRED);
    }

    public static function immutable(): self
    {
        return new self(self::IMMUTABLE);
    }

    public static function alreadyTaken(): self
    {
        return new self(self::ALREADY_TAKEN);
    }

    public static function reserved(): self
    {
        return new self(self::RESERVED);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            self::REQUIRED      => 'Organization slug is required.',
            self::IMMUTABLE     => 'Organization slug cannot be changed.',
            self::ALREADY_TAKEN => 'Organization slug is already taken.',
            self::RESERVED      => 'Organization slug is reserved.',
            default             => 'Unable to update organization slug.',
        };
    }
}
