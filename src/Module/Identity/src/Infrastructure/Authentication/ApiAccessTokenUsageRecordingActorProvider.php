<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Shared\Api\Authentication\CurrentUserActorInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

use function is_string;

/**
 * Records API token usage only after its owner has been resolved into an actor.
 */
final readonly class ApiAccessTokenUsageRecordingActorProvider implements AuthActorProviderInterface
{
    public function __construct(
        private DatabaseUserActorProvider $databaseUserActorProvider,
        private ApiAccessTokenQueryService $apiAccessTokenQueryService,
    ) {}

    public function getActor(TokenInterface $token): ?ActorInterface
    {
        $actor = $this->databaseUserActorProvider->getActor($token);
        if (! $actor instanceof ActorInterface || ! $token instanceof ApiAccessToken) {
            return $actor;
        }

        if ($actor instanceof CurrentUserActorInterface && $actor->currentUser()?->isBanned) {
            return $actor;
        }

        $userId = $token->getPayload()['user_id'] ?? null;
        if (! is_string($userId) || ! $this->apiAccessTokenQueryService->recordSuccessfulAuthentication($token->getId(), $userId)) {
            return null;
        }

        return $actor;
    }
}
