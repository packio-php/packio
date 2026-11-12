<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;

/** Mapper-only: the replacement ID is optional; reassignment eligibility requires application state. */
final readonly class DeleteProviderAuthorizationRequest
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function newAuthorizationId(): ?string
    {
        return ApiRequestPayload::optionalString($this->payload, 'new_authorization_id', 'newAuthorizationId');
    }
}
