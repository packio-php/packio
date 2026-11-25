<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class SourceProviderInstanceRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^(gitlab_self_hosted|forgejo_self_hosted|gitea)$#')]
        public string $providerType = '',
        #[Required]
        public string $label = '',
        #[Required]
        #[Regex('#^https://\S+$#', message: 'Base URL must start with https://.')]
        public string $baseUrl = '',
        public string $clientId = '',
        public string $clientSecret = '',
        #[Required]
        #[Regex('#^(oauth2|token|oauth2_and_token)$#')]
        public string $authMode = 'oauth2_and_token',
    ) {}
}
