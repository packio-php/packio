<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Request;

use Cake\Chronos\Chronos;
use Throwable;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

use function is_string;
use function trim;

final readonly class ApiAccessTokenIssueRequest
{
    public function __construct(
        #[Required]
        #[Length(min: 1, max: 100)]
        public string $name = '',
        #[Callback(method: 'validateExpiresAt')]
        public string $expiresAt = '',
    ) {}

    public static function validateExpiresAt(mixed $value): Result
    {
        $result = new Result();
        if (! is_string($value) || '' === trim($value)) {
            return $result;
        }

        try {
            new Chronos(trim($value));
        } catch (Throwable) {
            $result->addError('Expiration date is invalid.');
        }

        return $result;
    }
}
