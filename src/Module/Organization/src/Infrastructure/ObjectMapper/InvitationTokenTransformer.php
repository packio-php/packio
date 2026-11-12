<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\ObjectMapper;

use Packio\Organization\Application\Security\OrganizationInvitationTokens;
use Sirix\ObjectMapper\Contract\ValueTransformerInterface;

final readonly class InvitationTokenTransformer implements ValueTransformerInterface
{
    public function __construct(private OrganizationInvitationTokens $organizationInvitationTokens) {}

    public function transform(string $value): string
    {
        return $this->organizationInvitationTokens->decrypt($value);
    }
}
