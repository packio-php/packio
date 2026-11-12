<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final readonly class ProviderAuthorizationRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^(github|gitlab|gitlab_self_hosted|generic_git|bitbucket|codeberg)$#')]
        public string $provider = '',
        #[Required]
        public string $authorizationToken = '',
        #[Regex('#^$|^https?://\S+$#')]
        public string $instanceUrl = '',
        #[Regex('#^$|^[0-9a-fA-F-]{36}$#')]
        public string $providerInstanceId = '',
        #[Regex('#^$|^(bearer|basic)$#')]
        public string $authScheme = '',
    ) {}
}
