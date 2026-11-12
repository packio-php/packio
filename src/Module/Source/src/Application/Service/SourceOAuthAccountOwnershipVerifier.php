<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Dto\SourceOAuthAuthorizationData;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;

use function trim;

final readonly class SourceOAuthAccountOwnershipVerifier implements SourceOAuthAccountOwnershipVerifierInterface
{
    public function __construct(
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository,
        private SourceOAuthAccountSecrets $sourceOAuthAccountSecrets,
    ) {}

    public function verify(
        ActorContext $actorContext,
        string $currentUserId,
        string $provider,
        string $sourceOAuthAccountId,
    ): SourceOAuthAccountOwnershipDecision {
        $sourceOAuthAccountId = trim($sourceOAuthAccountId);
        if ('' === $sourceOAuthAccountId) {
            return SourceOAuthAccountOwnershipDecision::notFound();
        }

        $sourceOAuthAccount = $this->sourceOAuthAccountRepository->findById($sourceOAuthAccountId);
        if (! $sourceOAuthAccount instanceof SourceOAuthAccount) {
            return SourceOAuthAccountOwnershipDecision::notFound();
        }

        if (
            $sourceOAuthAccount->userId() !== $currentUserId
            || $sourceOAuthAccount->provider() !== $provider
            || ! $sourceOAuthAccount->isActive()
        ) {
            return SourceOAuthAccountOwnershipDecision::unavailable();
        }

        $accessToken = $this->sourceOAuthAccountSecrets->decryptAccessToken($sourceOAuthAccount);
        if (null === $accessToken || '' === trim($accessToken)) {
            return SourceOAuthAccountOwnershipDecision::tokenUnavailable();
        }

        return SourceOAuthAccountOwnershipDecision::allowed(new SourceOAuthAuthorizationData(
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            accessToken: $accessToken,
            providerUsername: $sourceOAuthAccount->providerUsername(),
            providerEmail: $sourceOAuthAccount->providerEmail(),
            providerInstanceId: $sourceOAuthAccount->providerInstanceId(),
            providerBaseUrl: $sourceOAuthAccount->providerBaseUrl(),
        ));
    }
}
