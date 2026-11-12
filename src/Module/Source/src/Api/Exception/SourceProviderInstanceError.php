<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use Throwable;

/** Transport-independent public mapping shared by API and web presenters. */
final readonly class SourceProviderInstanceError
{
    private function __construct(public int $status, public string $code, public string $message) {}

    public static function fromException(Throwable $throwable): self
    {
        if (! $throwable instanceof SourceProviderInstanceException) {
            return new self(500, 'internal_error', 'Unable to complete source provider instance operation.');
        }

        return match (true) {
            $throwable->isNotFound()  => new self(404, 'source_provider_instance_not_found', 'Source provider instance was not found.'),
            $throwable->isInUse()     => new self(409, 'source_provider_instance_in_use', 'Source provider instance conflicts with existing instances or dependencies.'),
            $throwable->isForbidden() => new self(403, 'forbidden', 'Forbidden.'),
            default                   => new self(422, 'source_provider_instance_invalid', 'Source provider instance input or state is invalid.'),
        };
    }
}
