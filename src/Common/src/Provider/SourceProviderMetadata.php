<?php

declare(strict_types=1);

namespace Common\Provider;

final readonly class SourceProviderMetadata
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $oauthProvider = null,
        public bool $supportsSelfHostedInstances = false,
    ) {}
}
