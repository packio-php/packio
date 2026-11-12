<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Validator;

use Stringable;

use function preg_match;
use function sprintf;
use function strlen;

final readonly class PasswordValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public bool $isValid, public array $errors) {}

    public static function valid(): self
    {
        return new self(true, []);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }
}

final class PasswordValidator
{
    private const int MIN_LENGTH = 8;

    public function validate(string|Stringable $password): PasswordValidationResult
    {
        $password = (string) $password;
        $errors   = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = sprintf('Password must be at least %d characters', self::MIN_LENGTH);
        }

        if (! preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one digit';
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return empty($errors)
            ? PasswordValidationResult::valid()
            : PasswordValidationResult::invalid($errors);
    }
}
