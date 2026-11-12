<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Identity\Api\Authentication\ApiAccessTokenContract;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Shared\Api\Dto\UserDto;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function is_string;
use function trim;

final readonly class DatabaseUserActorProvider implements AuthActorProviderInterface
{
    public function __construct(private UserRepositoryInterface $userRepository, private ObjectMapperInterface $objectMapper) {}

    public function getActor(TokenInterface $token): ?ActorInterface
    {
        $userId = $token->getPayload()['user_id'] ?? null;
        if (! is_string($userId)) {
            return null;
        }

        $userId = trim($userId);
        if ('' === $userId) {
            return null;
        }

        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User) {
            return null;
        }

        // Session tokens are bound to the password they were issued for; API tokens are revoked separately.
        if (ApiAccessTokenContract::STORAGE !== $token->getStorage()
            && ($token->getPayload()['credentials_fingerprint'] ?? null) !== $user->credentialsFingerprint()) {
            return null;
        }

        return new UserActor($userId, $user->roles(), $this->mapUser($user));
    }

    private function mapUser(User $user): UserDto
    {
        return $this->objectMapper->map($user, UserDto::class);
    }
}
