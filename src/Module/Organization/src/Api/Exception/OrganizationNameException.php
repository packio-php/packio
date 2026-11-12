<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Exception;

use RuntimeException;

final class OrganizationNameException extends RuntimeException implements OrganizationExceptionInterface
{
    private const string REQUIRED      = 'required';
    private const string TOO_LONG      = 'too_long';
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

    public static function tooLong(): self
    {
        return new self(self::TOO_LONG);
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
            self::REQUIRED      => 'Organization name is required.',
            self::TOO_LONG      => 'Organization name must not exceed 50 characters.',
            self::ALREADY_TAKEN => 'Organization name is already taken.',
            self::RESERVED      => 'Organization name is reserved.',
            default             => 'Unable to update organization name.',
        };
    }
}
