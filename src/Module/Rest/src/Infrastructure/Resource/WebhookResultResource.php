<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Webhook\Api\Dto\WebhookResultDto;

final readonly class WebhookResultResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(WebhookResultDto $webhookResultDto): array
    {
        return [
            'status_code' => $webhookResultDto->statusCode,
            'message'     => $webhookResultDto->message,
            'provider'    => '' !== $webhookResultDto->provider ? $webhookResultDto->provider : null,
        ];
    }
}
