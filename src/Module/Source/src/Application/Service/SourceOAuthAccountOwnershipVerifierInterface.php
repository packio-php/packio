<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;

interface SourceOAuthAccountOwnershipVerifierInterface
{
    public function verify(
        ActorContext $actorContext,
        string $currentUserId,
        string $provider,
        string $sourceOAuthAccountId,
    ): SourceOAuthAccountOwnershipDecision;
}
