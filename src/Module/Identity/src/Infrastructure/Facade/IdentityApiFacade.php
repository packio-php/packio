<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Facade;

use Common\Registry\RegistryProtocolPermissions;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Api\Dto\ApiAccessTokenRegenerationDto;
use Packio\Identity\Api\Dto\IssuedApiAccessTokenDto;
use Packio\Identity\Api\Dto\IssuedRegistryAccessTokenDto;
use Packio\Identity\Api\Dto\PasswordResetResultDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenRegenerationDto;
use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\Dto\UserListStatus as ApiUserListStatus;
use Packio\Identity\Api\Dto\UserModerationOutcome;
use Packio\Identity\Api\Dto\UserModerationResultDto;
use Packio\Identity\Api\EmailVerificationReasonType as ApiEmailVerificationReasonType;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Application\Command\PasswordResetService;
use Packio\Identity\Application\Command\RegistryActorContext;
use Packio\Identity\Application\Event\EmailVerificationRequested;
use Packio\Identity\Application\Port\AuthMailQueuePort;
use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\ApiAccessTokenRegeneration as ApiAccessTokenRegenerationResult;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Result\IssuedApiAccessToken;
use Packio\Identity\Application\Result\IssuedRegistryAccessToken;
use Packio\Identity\Application\Result\PasswordResetResult;
use Packio\Identity\Application\Result\RegistryAccessTokenResult;
use Packio\Identity\Domain\EmailVerificationReason as DomainEmailVerificationReason;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Value\UserListFilterCriteria;
use Packio\Identity\Domain\Value\UserListStatus;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;

final readonly class IdentityApiFacade implements IdentityApiFacadeInterface
{
    public function __construct(
        private IdentityCommandService $identityCommandService,
        private EmailVerificationService $emailVerificationService,
        private PasswordResetService $passwordResetService,
        private ApiAccessTokenCommandService $apiAccessTokenCommandService,
        private ApiAccessTokenQueryService $apiAccessTokenQueryService,
        private IdentityQueryService $identityQueryService,
        private UserRepositoryInterface $userRepository,
        private EmailVerificationCodeRepositoryInterface $emailVerificationCodeRepository,
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ObjectMapperInterface $objectMapper,
        private AuthMailQueuePort $authMailQueuePort,
        private AuthManagerInterface $authManager,
    ) {}

    public function createUser(string $email, string $displayName, string $password, array $roles = ['user']): UserDto
    {
        return $this->mapUser($this->identityCommandService->createUser(
            $email,
            $displayName,
            $password,
            $roles,
        ));
    }

    public function authenticateCredentials(string $email, string $password): ?UserDto
    {
        $user = $this->identityQueryService->authenticateCredentials($email, $password);

        return $user instanceof IdentityUserResult ? $this->mapIdentityUserResult($user) : null;
    }

    public function requestEmailVerification(
        string $userId,
        bool $force,
        ApiEmailVerificationReasonType $apiEmailVerificationReasonType
    ): void {
        $this->eventDispatcher->dispatch(
            new EmailVerificationRequested(
                Uuid::fromString($userId),
                $force,
                DomainEmailVerificationReason::from($apiEmailVerificationReasonType->value),
            ),
        );
    }

    public function emailVerificationRemainingResendDelay(string $userId): int
    {
        return $this->emailVerificationService->remainingResendDelay($userId);
    }

    public function markUserEmailAsVerified(string $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User) {
            return false;
        }

        $user->markEmailAsVerified();
        $this->userRepository->save($user);

        return true;
    }

    public function currentUser(?ServerRequestInterface $serverRequest = null): ?UserDto
    {
        if (! $serverRequest instanceof ServerRequestInterface) {
            return null;
        }

        $token = $this->authManager->token($serverRequest);
        if (! $token instanceof TokenInterface) {
            return null;
        }

        $userId = (string) ($token->getPayload()['user_id'] ?? '');
        if ('' === $userId) {
            return null;
        }

        return $this->findUserById($userId);
    }

    public function findUserById(string $id): ?UserDto
    {
        $user = $this->identityQueryService->findUserById($id);

        return $user instanceof IdentityUserResult ? $this->mapIdentityUserResult($user) : null;
    }

    public function updateUser(string $userId, string $email, string $displayName, array $roles): ?UserDto
    {
        $user = $this->identityCommandService->updateUser(
            $userId,
            $email,
            $displayName,
            $roles,
        );

        return $user instanceof User ? $this->mapUser($user) : null;
    }

    public function banUser(string $userId, string $actorId, string $reason): UserModerationResultDto
    {
        return new UserModerationResultDto(UserModerationOutcome::from($this->identityCommandService->banUser($userId, $actorId, $reason)->value));
    }

    public function unbanUser(string $userId, string $actorId): UserModerationResultDto
    {
        return new UserModerationResultDto(UserModerationOutcome::from($this->identityCommandService->unbanUser($userId, $actorId)->value));
    }

    public function findUsersPaginated(PaginationCriteria $paginationCriteria, UserListFilterDto $userListFilterDto): PaginatedResultDto
    {
        $result = $this->identityQueryService->findUsersPaginated(
            $paginationCriteria,
            new UserListFilterCriteria($userListFilterDto->status instanceof ApiUserListStatus ? UserListStatus::from($userListFilterDto->status->value) : null),
        );

        return new PaginatedResultDto(
            items: array_map($this->mapIdentityUserResult(...), $result['items']),
            total: $result['total'],
            currentPage: $result['currentPage'],
            perPage: $result['perPage'],
        );
    }

    public function findUsersByIds(array $ids): array
    {
        return array_map($this->mapIdentityUserResult(...), $this->identityQueryService->findUsersByIds($ids));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<UserDto>
     */
    public function findAllUsersByIds(array $ids): array
    {
        return array_map($this->mapIdentityUserResult(...), $this->identityQueryService->findAllUsersByIds($ids));
    }

    public function usersCount(): int
    {
        return $this->identityQueryService->usersCount();
    }

    public function adminsCount(): int
    {
        return $this->identityQueryService->adminsCount();
    }

    public function activeAdminsCount(): int
    {
        return $this->identityQueryService->activeAdminsCount();
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        return $this->identityCommandService->changePassword(
            $userId,
            $currentPassword,
            $newPassword,
        );
    }

    public function setPassword(string $userId, string $newPassword): bool
    {
        return $this->identityCommandService->setPassword($userId, $newPassword);
    }

    public function issueRegistryAccessTokenInContext(
        ActorContext $actorContext,
        string $name,
        array $permissions = [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE],
    ): IssuedRegistryAccessTokenDto {
        return $this->mapIssuedRegistryAccessToken($this->identityCommandService->issueRegistryAccessTokenInContext(
            new RegistryActorContext(
                organizationId: $actorContext->organizationId,
                actorId: $actorContext->actorId,
                actorRole: $actorContext->actorRole,
            ),
            $name,
            $permissions,
        ));
    }

    public function authenticateRegistryAccessToken(?string $plainTextToken): ?RegistryAccessTokenDto
    {
        $token = $this->identityQueryService->authenticateRegistryAccessToken(
            $plainTextToken,
        );

        return $token instanceof RegistryAccessTokenResult
            ? $this->mapRegistryAccessTokenResult($token)
            : null;
    }

    /** @return list<RegistryAccessTokenDto> */
    public function registryAccessTokensForOrganization(string $organizationId): array
    {
        return array_map(
            $this->mapRegistryAccessTokenResult(...),
            $this->identityQueryService->registryAccessTokensForOrganization($organizationId),
        );
    }

    public function regenerateRegistryAccessTokenInContext(ActorContext $actorContext, string $tokenId): RegistryAccessTokenRegenerationDto
    {
        $registryAccessTokenRegeneration = $this->identityCommandService->regenerateRegistryAccessTokenInContext(
            new RegistryActorContext(
                organizationId: $actorContext->organizationId,
                actorId: $actorContext->actorId,
                actorRole: $actorContext->actorRole,
            ),
            $tokenId,
        );
        if (! $registryAccessTokenRegeneration->success || ! $registryAccessTokenRegeneration->issued instanceof IssuedRegistryAccessToken || null === $registryAccessTokenRegeneration->revokedId) {
            return $registryAccessTokenRegeneration->isPersistenceFailed()
                ? RegistryAccessTokenRegenerationDto::persistenceFailed()
                : RegistryAccessTokenRegenerationDto::notFound();
        }

        return RegistryAccessTokenRegenerationDto::regenerated(
            $this->mapIssuedRegistryAccessToken($registryAccessTokenRegeneration->issued),
            $registryAccessTokenRegeneration->revokedId,
        );
    }

    public function revokeRegistryAccessTokenInContext(ActorContext $actorContext, string $id): bool
    {
        return $this->identityCommandService->revokeRegistryAccessTokenInContext(
            new RegistryActorContext(
                organizationId: $actorContext->organizationId,
                actorId: $actorContext->actorId,
                actorRole: $actorContext->actorRole,
            ),
            $id,
        );
    }

    public function revokeRegistryTokensForOrganization(string $organizationId): int
    {
        return $this->identityCommandService->revokeRegistryTokensForOrganization($organizationId);
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function disconnectOAuthAccount(string $userId, string $oauthAccountId): array
    {
        return $this->identityCommandService->disconnectOAuthAccount(
            $userId,
            $oauthAccountId,
        );
    }

    public function cleanupExpiredEmailVerificationCodes(): int
    {
        return $this->emailVerificationCodeRepository->deleteExpired();
    }

    public function requestPasswordReset(string $email, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->authMailQueuePort->passwordReset($email, $ip, $userAgent);
    }

    public function validatePasswordResetToken(string $selector, string $token): PasswordResetResultDto
    {
        return $this->mapPasswordResetResult(
            $this->passwordResetService->validateResetToken($selector, $token),
        );
    }

    public function cleanupExpiredPasswordResetTokens(): int
    {
        return $this->passwordResetTokenRepository->deleteExpired();
    }

    public function issueApiAccessTokenForUser(
        string $userId,
        string $name,
        array $scopes = ['read'],
        ?string $expiresAt = null,
    ): IssuedApiAccessTokenDto {
        return $this->mapIssuedApiAccessToken($this->apiAccessTokenCommandService->issueForUser(
            $userId,
            $name,
            $scopes,
            $expiresAt,
        ));
    }

    public function apiAccessTokensForUser(string $userId): array
    {
        return array_map(
            $this->mapApiAccessTokenResult(...),
            $this->apiAccessTokenQueryService->tokensForUser($userId),
        );
    }

    public function revokeApiAccessTokenForUser(string $userId, string $tokenId): bool
    {
        return $this->apiAccessTokenCommandService->revokeForUser($userId, $tokenId);
    }

    public function regenerateApiAccessTokenForUser(string $userId, string $tokenId): ApiAccessTokenRegenerationDto
    {
        return $this->mapApiAccessTokenRegeneration(
            $this->apiAccessTokenCommandService->regenerateForUser($userId, $tokenId),
        );
    }

    public function deleteRevokedApiAccessTokenForUser(string $userId, string $tokenId): bool
    {
        return $this->apiAccessTokenCommandService->deleteRevokedForUser($userId, $tokenId);
    }

    private function mapUser(User $user): UserDto
    {
        return $this->objectMapper->map($user, UserDto::class);
    }

    private function mapIssuedRegistryAccessToken(IssuedRegistryAccessToken $issuedRegistryAccessToken): IssuedRegistryAccessTokenDto
    {
        return $this->objectMapper->map($issuedRegistryAccessToken, IssuedRegistryAccessTokenDto::class);
    }

    private function mapIssuedApiAccessToken(IssuedApiAccessToken $issuedApiAccessToken): IssuedApiAccessTokenDto
    {
        return $this->objectMapper->map($issuedApiAccessToken, IssuedApiAccessTokenDto::class);
    }

    private function mapApiAccessTokenRegeneration(
        ApiAccessTokenRegenerationResult $apiAccessTokenRegenerationResult
    ): ApiAccessTokenRegenerationDto {
        if (! $apiAccessTokenRegenerationResult->success || ! $apiAccessTokenRegenerationResult->issued instanceof IssuedApiAccessToken || null === $apiAccessTokenRegenerationResult->revokedId) {
            return $apiAccessTokenRegenerationResult->isPersistenceFailed()
                ? ApiAccessTokenRegenerationDto::persistenceFailed()
                : ApiAccessTokenRegenerationDto::notFound();
        }

        return ApiAccessTokenRegenerationDto::regenerated(
            $this->mapIssuedApiAccessToken($apiAccessTokenRegenerationResult->issued),
            $apiAccessTokenRegenerationResult->revokedId,
        );
    }

    private function mapApiAccessTokenResult(ApiAccessTokenResult $apiAccessTokenResult): ApiAccessTokenDto
    {
        return $this->objectMapper->map($apiAccessTokenResult, ApiAccessTokenDto::class);
    }

    private function mapIdentityUserResult(IdentityUserResult $identityUserResult): UserDto
    {
        return $this->objectMapper->map($identityUserResult, UserDto::class);
    }

    private function mapRegistryAccessTokenResult(RegistryAccessTokenResult $registryAccessTokenResult): RegistryAccessTokenDto
    {
        return $this->objectMapper->map($registryAccessTokenResult, RegistryAccessTokenDto::class);
    }

    private function mapPasswordResetResult(PasswordResetResult $passwordResetResult): PasswordResetResultDto
    {
        return $this->objectMapper->map($passwordResetResult, PasswordResetResultDto::class);
    }
}
