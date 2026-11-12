<?php

declare(strict_types=1);

namespace Packio\Identity\Api;

use Common\Registry\RegistryProtocolPermissions;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Api\Dto\ApiAccessTokenRegenerationDto;
use Packio\Identity\Api\Dto\IssuedApiAccessTokenDto;
use Packio\Identity\Api\Dto\IssuedRegistryAccessTokenDto;
use Packio\Identity\Api\Dto\PasswordResetResultDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenRegenerationDto;
use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\Dto\UserModerationResultDto;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Psr\Http\Message\ServerRequestInterface;

interface IdentityApiFacadeInterface
{
    /**
     * @param list<string> $roles
     */
    public function createUser(string $email, string $displayName, string $password, array $roles = ['user']): UserDto;

    public function authenticateCredentials(string $email, string $password): ?UserDto;

    public function requestEmailVerification(string $userId, bool $force, EmailVerificationReasonType $emailVerificationReasonType): void;

    public function emailVerificationRemainingResendDelay(string $userId): int;

    public function markUserEmailAsVerified(string $userId): bool;

    public function currentUser(?ServerRequestInterface $serverRequest = null): ?UserDto;

    public function findUserById(string $id): ?UserDto;

    /**
     * @param list<string> $roles
     */
    public function updateUser(string $userId, string $email, string $displayName, array $roles): ?UserDto;

    public function banUser(string $userId, string $actorId, string $reason): UserModerationResultDto;

    public function unbanUser(string $userId, string $actorId): UserModerationResultDto;

    /**
     * @phpstan-return PaginatedResultDto<UserDto>
     */
    public function findUsersPaginated(PaginationCriteria $paginationCriteria, UserListFilterDto $userListFilterDto): PaginatedResultDto;

    /**
     * @param list<string> $ids
     *
     * @return list<UserDto>
     */
    public function findUsersByIds(array $ids): array;

    /**
     * Batch lookup for server-derived IDs, without the admin selector limit.
     *
     * @param list<string> $ids
     *
     * @return list<UserDto>
     */
    public function findAllUsersByIds(array $ids): array;

    public function usersCount(): int;

    /** Includes banned users with the admin role. */
    public function adminsCount(): int;

    /** Counts only non-banned users with the admin role. */
    public function activeAdminsCount(): int;

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool;

    public function setPassword(string $userId, string $newPassword): bool;

    /**
     * @param list<string> $permissions
     */
    public function issueRegistryAccessTokenInContext(
        ActorContext $actorContext,
        string $name,
        array $permissions = [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE],
    ): IssuedRegistryAccessTokenDto;

    public function authenticateRegistryAccessToken(?string $plainTextToken): ?RegistryAccessTokenDto;

    /** @return list<RegistryAccessTokenDto> */
    public function registryAccessTokensForOrganization(string $organizationId): array;

    public function regenerateRegistryAccessTokenInContext(ActorContext $actorContext, string $tokenId): RegistryAccessTokenRegenerationDto;

    public function revokeRegistryAccessTokenInContext(ActorContext $actorContext, string $id): bool;

    public function revokeRegistryTokensForOrganization(string $organizationId): int;

    /**
     * @return array{success: bool, message?: string}
     */
    public function disconnectOAuthAccount(string $userId, string $oauthAccountId): array;

    public function cleanupExpiredEmailVerificationCodes(): int;

    public function requestPasswordReset(string $email, ?string $ip = null, ?string $userAgent = null): void;

    public function validatePasswordResetToken(string $selector, string $token): PasswordResetResultDto;

    public function cleanupExpiredPasswordResetTokens(): int;

    /**
     * @param list<string> $scopes
     */
    public function issueApiAccessTokenForUser(
        string $userId,
        string $name,
        array $scopes = ['read'],
        ?string $expiresAt = null,
    ): IssuedApiAccessTokenDto;

    /**
     * @return list<ApiAccessTokenDto>
     */
    public function apiAccessTokensForUser(string $userId): array;

    public function revokeApiAccessTokenForUser(string $userId, string $tokenId): bool;

    public function regenerateApiAccessTokenForUser(string $userId, string $tokenId): ApiAccessTokenRegenerationDto;

    public function deleteRevokedApiAccessTokenForUser(string $userId, string $tokenId): bool;
}
