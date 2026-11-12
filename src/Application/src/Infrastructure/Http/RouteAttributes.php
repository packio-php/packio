<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Http;

use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

use function is_string;
use function sprintf;

final class RouteAttributes
{
    public static function requireString(ServerRequestInterface $serverRequest, string $name): string
    {
        $value = $serverRequest->getAttribute($name);

        if (! is_string($value) || '' === $value) {
            throw new LogicException(sprintf('Route attribute "%s" is missing or malformed.', $name));
        }

        return $value;
    }

    public static function uuid(ServerRequestInterface $serverRequest, string $name): string
    {
        $value = $serverRequest->getAttribute($name);

        if (! is_string($value)) {
            return '';
        }

        // canonical form: isValid() also accepts urn:uuid: and {braces}, which Postgres rejects
        return Uuid::isValid($value) ? Uuid::fromString($value)->toString() : '';
    }
}
