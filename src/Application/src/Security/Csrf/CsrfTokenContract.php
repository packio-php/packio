<?php

declare(strict_types=1);

namespace Packio\Application\Security\Csrf;

final class CsrfTokenContract
{
    public const string SESSION_KEY = '__csrf';

    public const string HEADER = 'X-CSRF-Token';

    /**
     * Methods that carry no side effects and therefore never require CSRF protection.
     *
     * Any method not listed here is treated as unsafe on purpose: unknown or
     * future methods must always be validated.
     *
     * @var list<string>
     */
    public const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public const int RANDOM_BYTES_LENGTH = 32;

    public const int TOKEN_LENGTH = self::RANDOM_BYTES_LENGTH * 2;

    private function __construct() {}
}
