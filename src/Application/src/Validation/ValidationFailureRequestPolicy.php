<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_string;

final readonly class ValidationFailureRequestPolicy
{
    public function __construct(private RequestPresentationPolicy $requestPresentationPolicy = new RequestPresentationPolicy()) {}

    public function wantsJson(ServerRequestInterface $serverRequest): bool
    {
        return $this->requestPresentationPolicy->wantsJson($serverRequest);
    }

    public function isApiPath(ServerRequestInterface $serverRequest): bool
    {
        return $this->requestPresentationPolicy->isApiRequest($serverRequest);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function allowedOldInput(array $input, mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $allowed = [];
        foreach ($fields as $field) {
            if (! is_string($field) || ! array_key_exists($field, $input)) {
                continue;
            }

            $allowed[$field] = $input[$field];
        }

        return $allowed;
    }
}
