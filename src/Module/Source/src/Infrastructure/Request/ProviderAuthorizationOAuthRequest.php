<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\Uuid;

final readonly class ProviderAuthorizationOAuthRequest
{
    public function __construct(
        #[Required]
        #[Regex('#^(github|gitlab|gitlab_self_hosted|bitbucket|codeberg)$#')]
        public string $provider = '',
        #[Uuid(skipOnEmpty: true)]
        public string $oauthAccountId = '',
        #[Uuid(skipOnEmpty: true)]
        public string $sourceOauthAccountId = '',
    ) {}
}
