<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\WebhookRegistrationResultDto;

final readonly class WebhookRegistrationResultResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(WebhookRegistrationResultDto $webhookRegistrationResultDto): array
    {
        return [
            'success'             => $webhookRegistrationResultDto->success,
            'external_id'         => '' !== $webhookRegistrationResultDto->externalId ? $webhookRegistrationResultDto->externalId : null,
            'message'             => '' !== $webhookRegistrationResultDto->message ? $webhookRegistrationResultDto->message : null,
            'already_exists'      => $webhookRegistrationResultDto->alreadyExists,
            'auth_health'         => $webhookRegistrationResultDto->authHealth,
            'auth_failure_reason' => $webhookRegistrationResultDto->authFailureReason,
        ];
    }
}
