<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Dto;

final readonly class SourceOAuthContext
{
    public function __construct(public string $organizationSlug, public bool $popup, public string $providerInstanceId = '') {}
}
