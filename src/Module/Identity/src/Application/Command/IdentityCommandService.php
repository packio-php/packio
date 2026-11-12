<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Common\Exception\PublicValidationException;
use Common\Registry\RegistryProtocolPermissions;
use InvalidArgumentException;
use Packio\Identity\Api\UserBanReason;
use Packio\Identity\Application\Result\IssuedRegistryAccessToken;
use Packio\Identity\Application\Result\RegistryAccessTokenRegeneration;
use Packio\Identity\Application\Validator\PasswordValidator;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Value\UserModerationOutcome;
use RuntimeException;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function count;
use function filter_var;
use function implode;
use function in_array;
use function mb_strlen;
use function password_hash;
use function password_verify;
use function preg_match;
use function trim;

final readonly class IdentityCommandService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RegistryTokenCommandService $registryTokenCommandService,
        private UserOAuthAccountRepositoryInterface $userOAuthAccountRepository,
        private PasswordValidator $passwordValidator,
    ) {}

    public function registerUser(string $email, string $displayName, string $password): ?User
    {
        $this->assertValidEmail($email);
        $this->assertValidPassword($password);

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($this->userRepository->findByEmail($email) instanceof User) {
            return null;
        }

        try {
            return $this->userRepository->save(
                User::register(
                    email: $email,
                    displayName: $displayName,
                    passwordHash: $passwordHash,
                    roles: ['user'],
                ),
            );
        } catch (RepositoryPersistException $repositoryPersistException) {
            if ($this->userRepository->findByEmail($email) instanceof User) {
                return null;
            }

            throw $repositoryPersistException;
        }
    }

    /**
     * @param list<string> $roles
     */
    public function createUser(string $email, string $displayName, string $password, array $roles = ['user']): User
    {
        $this->assertValidEmail($email);
        $this->assertValidPassword($password);

        $entity = $this->userRepository->findByEmail($email);

        if ($entity instanceof User) {
            $entity->updateProfile($email, $displayName);
            $entity->updatePasswordHash(password_hash($password, PASSWORD_DEFAULT));
            $entity->replaceRoles($roles);

            return $this->userRepository->save($entity);
        }

        return $this->userRepository->save(
            User::register(
                email: $email,
                displayName: $displayName,
                passwordHash: password_hash($password, PASSWORD_DEFAULT),
                roles: $roles,
            ),
        );
    }

    /**
     * @param list<string> $roles
     */
    public function createUserWithoutPassword(string $email, string $displayName, array $roles = ['user']): User
    {
        $this->assertValidEmail($email);

        $entity = $this->userRepository->findByEmail($email);

        if ($entity instanceof User) {
            $entity->updateProfile($email, $displayName);
            $entity->replaceRoles($roles);

            return $this->userRepository->save($entity);
        }

        return $this->userRepository->save(
            User::register(
                email: $email,
                displayName: $displayName,
                passwordHash: '',
                roles: $roles,
            ),
        );
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $entity = $this->userRepository->findById($userId);
        if (! $entity instanceof User) {
            return false;
        }

        if (! password_verify($currentPassword, $entity->passwordHash())) {
            return false;
        }

        $this->assertValidPassword($newPassword);

        $entity->updatePasswordHash(
            password_hash($newPassword, PASSWORD_DEFAULT),
        );
        $this->userRepository->save($entity);

        return true;
    }

    public function setPassword(string $userId, string $newPassword): bool
    {
        $entity = $this->userRepository->findById($userId);
        if (! $entity instanceof User) {
            return false;
        }

        $this->assertValidPassword($newPassword);

        $entity->updatePasswordHash(
            password_hash($newPassword, PASSWORD_DEFAULT),
        );
        $this->userRepository->save($entity);

        return true;
    }

    /**
     * @param list<string> $roles
     */
    public function updateUser(string $userId, string $email, string $displayName, array $roles): ?User
    {
        $this->assertValidEmail($email);
        $this->assertValidDisplayName($displayName);

        $entity = $this->userRepository->findById($userId);
        if (! $entity instanceof User) {
            return null;
        }

        $existing = $this->userRepository->findByEmail($email);
        if ($existing instanceof User && $existing->getIdentifier()->toString() !== $entity->getIdentifier()->toString()) {
            throw new InvalidArgumentException('Email is already in use.');
        }

        // shortcut: no lock, two admins demoting each other at once can both pass; recover with user:make-admin, add the moderation advisory lock if it ever happens.
        if (
            in_array('admin', $entity->roles(), true)
            && ! $entity->isBanned()
            && ! in_array('admin', $roles, true)
            && $this->userRepository->activeAdminsCount() <= 1
        ) {
            throw new PublicValidationException('The last active administrator cannot lose the admin role.');
        }

        $entity->updateProfile($email, $displayName);
        $entity->replaceRoles($roles);

        return $this->userRepository->save($entity);
    }

    public function banUser(string $userId, string $actorId, string $reason): UserModerationOutcome
    {
        $normalizedReason = trim($reason);
        if (mb_strlen($normalizedReason) < UserBanReason::MIN_LENGTH || mb_strlen($normalizedReason) > UserBanReason::MAX_LENGTH) {
            throw new InvalidArgumentException('Ban reason has an invalid length.');
        }

        return $this->userRepository->banUser($userId, $actorId, $normalizedReason);
    }

    public function unbanUser(string $userId, string $actorId): UserModerationOutcome
    {
        if ('' === trim($actorId)) {
            throw new InvalidArgumentException('Actor ID is required.');
        }

        return $this->userRepository->unbanUser($userId);
    }

    /**
     * @param list<string> $permissions
     */
    public function issueRegistryAccessTokenInContext(
        RegistryActorContext $registryActorContext,
        string $name,
        array $permissions = [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE],
    ): IssuedRegistryAccessToken {
        $this->assertOwnerContext($registryActorContext);

        return $this->registryTokenCommandService->issueForOrganizationUser(
            $registryActorContext->organizationId,
            $registryActorContext->actorId,
            $name,
            $permissions,
        );
    }

    public function regenerateRegistryAccessTokenInContext(
        RegistryActorContext $registryActorContext,
        string $tokenId
    ): RegistryAccessTokenRegeneration {
        $this->assertOwnerContext($registryActorContext);

        return $this->registryTokenCommandService->regenerateForOrganizationUser(
            $registryActorContext->organizationId,
            $registryActorContext->actorId,
            $tokenId,
        );
    }

    public function revokeRegistryAccessTokenInContext(RegistryActorContext $registryActorContext, string $id): bool
    {
        $this->assertOwnerContext($registryActorContext);

        return $this->registryTokenCommandService->revokeForOrganizationUser(
            $id,
            $registryActorContext->organizationId,
            $registryActorContext->actorId,
        );
    }

    /**
     * @param list<string> $permissions
     */
    public function issueRegistryTokenForUser(
        string $organizationId,
        string $userId,
        string $name,
        array $permissions = [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE]
    ): IssuedRegistryAccessToken {
        return $this->registryTokenCommandService->issueForOrganizationUser(
            $organizationId,
            $userId,
            $name,
            $permissions,
        );
    }

    public function revokeRegistryTokensForOrganization(string $organizationId): int
    {
        return $this->registryTokenCommandService->revokeForOrganization($organizationId);
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function disconnectOAuthAccount(string $userId, string $oauthAccountId): array
    {
        $entity = $this->userRepository->findById($userId);
        if (! $entity instanceof User) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $account = $this->userOAuthAccountRepository->findById($oauthAccountId);
        if (! $account instanceof UserOAuthAccount) {
            return [
                'success' => false,
                'message' => 'OAuth account not found.',
            ];
        }

        if ($account->userId() !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only disconnect your own OAuth accounts.',
            ];
        }

        $oauthAccounts = $this->userOAuthAccountRepository->findAllByUserId($userId);
        $hasPassword   = '' !== trim($entity->passwordHash());

        if (! $hasPassword && count($oauthAccounts) <= 1) {
            return [
                'success' => false,
                'message' => 'Set a password or connect another provider before disconnecting your only sign-in method.',
            ];
        }

        $this->userOAuthAccountRepository->deleteAndFlush($account);

        return [
            'success' => true,
        ];
    }

    private function assertOwnerContext(RegistryActorContext $registryActorContext): void
    {
        if (! $registryActorContext->isValid()) {
            throw new RuntimeException('Owner actor context is required.');
        }
    }

    private function assertValidPassword(string $password): void
    {
        $passwordValidationResult = $this->passwordValidator->validate($password);
        if (! $passwordValidationResult->isValid) {
            throw new InvalidArgumentException(implode(', ', $passwordValidationResult->errors));
        }
    }

    private function assertValidEmail(string $email): void
    {
        $normalizedEmail = trim($email);

        if ('' === $normalizedEmail) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (1 !== preg_match('//u', $normalizedEmail)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (false === filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
    }

    private function assertValidDisplayName(string $displayName): void
    {
        if ('' === trim($displayName)) {
            throw new InvalidArgumentException('Display name is required.');
        }
    }
}
