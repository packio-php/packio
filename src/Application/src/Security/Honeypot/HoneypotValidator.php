<?php

declare(strict_types=1);

namespace Packio\Application\Security\Honeypot;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;
use function str_starts_with;

final readonly class HoneypotValidator implements HoneypotValidatorInterface
{
    public function validate(ServerRequestInterface $serverRequest): bool
    {
        $body = $serverRequest->getParsedBody();

        if (! is_array($body)) {
            return true;
        }

        foreach ($body as $key => $value) {
            if (is_string($key) && str_starts_with($key, HoneypotFieldContract::FIELD_PREFIX)) {
                return '' === $value || null === $value;
            }
        }

        return true;
    }
}
