<?php

declare(strict_types=1);

namespace Packio\Webhook\Application\Dto;

final readonly class SourceData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $provider,
        public string $url,
        public bool $active,
        public string $webhookSecret,
    ) {}
}
