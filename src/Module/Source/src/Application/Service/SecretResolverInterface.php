<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;

interface SecretResolverInterface extends SourceSecretResolverInterface
{
    public function resolveAuthorizationEntitySecret(
        ProviderAuthorization $providerAuthorization,
        ?SourceOAuthAccount $sourceOAuthAccount = null
    ): ?string;
}
