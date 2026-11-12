<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Query;

use Cake\Chronos\Chronos;
use Packio\Identity\Application\Command\IdentityCredentialsInterface;
use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Result\RegistryAccessTokenResult;
use Packio\Identity\Application\Result\UserOAuthAccountResult;
use Packio\Identity\Application\Security\PasswordVerification;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Value\UserListFilterCriteria;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

use function array_map;
use function hash;
use function strtolower;
use function trim;

final readonly class IdentityQueryService implements IdentityCredentialsInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RegistryAccessTokenRepositoryInterface $registryAccessTokenRepository,
        private UserOAuthAccountRepositoryInterface $userOAuthAccountRepository,
        private RegistryAccessTokenSecrets $registryAccessTokenSecrets,
    ) {}

    public function authenticateCredentials(string $email, string $password): ?IdentityUserResult
    {
        $entity = $this->userRepository->findByEmail(strtolower(trim($email)));
        $valid  = PasswordVerification::matches($password, $entity?->passwordHash());
        if (! $entity instanceof User || ! $valid) {
            return null;
        }

        return $this->mapUser($entity);
    }

    public function findUserById(string $id): ?IdentityUserResult
    {
        $entity = $this->userRepository->findById($id);

        return $entity instanceof User ? $this->mapUser($entity) : null;
    }

    public function credentialsFingerprint(string $userId): ?string
    {
        return $this->userRepository->findById($userId)?->credentialsFingerprint();
    }

    /**
     * @return array{items: list<IdentityUserResult>, total: int, currentPage: int, perPage: int}
     */
    public function findUsersPaginated(PaginationCriteria $paginationCriteria, UserListFilterCriteria $userListFilterCriteria): array
    {
        $result = $this->userRepository->findPaginated($paginationCriteria, $userListFilterCriteria);

        return [
            'items'       => array_map($this->mapUser(...), $result['items']),
            'total'       => $result['total'],
            'currentPage' => $result['currentPage'],
            'perPage'     => $result['perPage'],
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<IdentityUserResult>
     */
    public function findUsersByIds(array $ids): array
    {
        return array_map($this->mapUser(...), $this->userRepository->findByIds($ids));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<IdentityUserResult>
     */
    public function findAllUsersByIds(array $ids): array
    {
        return array_map($this->mapUser(...), $this->userRepository->findAllByIds($ids));
    }

    public function usersCount(): int
    {
        return $this->userRepository->countAll();
    }

    public function adminsCount(): int
    {
        return $this->userRepository->countAdmins();
    }

    public function activeAdminsCount(): int
    {
        return $this->userRepository->activeAdminsCount();
    }

    public function authenticateRegistryAccessToken(?string $plainTextToken): ?RegistryAccessTokenResult
    {
        $token = trim((string) $plainTextToken);
        if ('' === $token) {
            return null;
        }

        $entity = $this->registryAccessTokenRepository->findByTokenHash(hash('sha256', $token));
        if (! $entity instanceof RegistryAccessToken) {
            return null;
        }

        if ('' === $entity->organizationId()) {
            return null;
        }

        $owner = $this->userRepository->findById($entity->ownerUserId());
        if (! $owner instanceof User) {
            return null;
        }

        if ($owner->isBanned()) {
            return $this->mapRegistryAccessToken($entity, isOwnerBanned: true);
        }

        $entity->markUsed(new Chronos());
        $entity = $this->registryAccessTokenRepository->save($entity);

        return $this->mapRegistryAccessToken($entity);
    }

    /** @return list<RegistryAccessTokenResult> */
    public function registryAccessTokensForOrganization(string $organizationId): array
    {
        return array_map(
            $this->mapRegistryAccessToken(...),
            $this->registryAccessTokenRepository->latestForOrganization($organizationId),
        );
    }

    /**
     * @return list<UserOAuthAccountResult>
     */
    public function findOAuthAccountsByUserId(string $userId): array
    {
        $accounts = $this->userOAuthAccountRepository->findAllByUserId($userId);

        return array_map(
            static fn ($account): UserOAuthAccountResult => new UserOAuthAccountResult(
                id: $account->getIdentifier()->toString(),
                provider: $account->provider()->value,
                providerUsername: $account->providerUsername(),
                providerEmail: $account->providerEmail(),
            ),
            $accounts,
        );
    }

    private function mapUser(User $user): IdentityUserResult
    {
        return new IdentityUserResult(
            id: $user->getIdentifier()->toString(),
            email: $user->email(),
            displayName: $user->displayName(),
            roles: $user->roles(),
            createdAt: $user->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $user->getUpdatedAt()?->format(DATE_ATOM) ?? '',
            hasPassword: '' !== $user->passwordHash(),
            isEmailVerified: $user->emailVerifiedAt() instanceof Chronos,
            isBanned: $user->isBanned(),
            banReason: $user->banReason(),
            bannedAt: $user->bannedAt()?->format(DATE_ATOM),
            bannedByUserId: $user->bannedByUserId(),
        );
    }

    private function mapRegistryAccessToken(
        RegistryAccessToken $registryAccessToken,
        bool $isOwnerBanned = false
    ): RegistryAccessTokenResult {
        $plainToken = $this->registryAccessTokenSecrets->decryptToken($registryAccessToken);

        return new RegistryAccessTokenResult(
            id: $registryAccessToken->getIdentifier()->toString(),
            name: $registryAccessToken->name(),
            permissions: $registryAccessToken->permissions(),
            createdAt: $registryAccessToken->getCreatedAt()->format(DATE_ATOM),
            lastUsedAt: $registryAccessToken->lastUsedAt()?->format(DATE_ATOM) ?? '',
            organizationId: $registryAccessToken->organizationId(),
            maskedToken: $registryAccessToken->maskedToken(),
            plainToken: $plainToken ?? '',
            isOwnerBanned: $isOwnerBanned,
        );
    }
}
