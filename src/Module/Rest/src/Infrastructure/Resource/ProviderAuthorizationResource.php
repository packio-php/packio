<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\ProviderAuthorizationDto;

final readonly class ProviderAuthorizationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(ProviderAuthorizationDto $providerAuthorizationDto): array
    {
        return [
            'id'                         => $providerAuthorizationDto->id,
            'organization_id'            => $providerAuthorizationDto->organizationId,
            'provider'                   => $providerAuthorizationDto->provider,
            'auth_strategy'              => $providerAuthorizationDto->authStrategy,
            'status'                     => $providerAuthorizationDto->status,
            'account_name'               => '' !== $providerAuthorizationDto->accountName ? $providerAuthorizationDto->accountName : null,
            'configured'                 => $providerAuthorizationDto->configured,
            'masked_value'               => '' !== $providerAuthorizationDto->maskedValue ? $providerAuthorizationDto->maskedValue : null,
            'source_oauth_account_id'    => $providerAuthorizationDto->sourceOAuthAccountId,
            'provider_instance_id'       => $providerAuthorizationDto->providerInstanceId,
            'auth_health'                => $providerAuthorizationDto->authHealth,
            'auth_failure_reason'        => $providerAuthorizationDto->authFailureReason,
            'last_token_refresh_at'      => $providerAuthorizationDto->lastTokenRefreshAt,
        ];
    }
}
