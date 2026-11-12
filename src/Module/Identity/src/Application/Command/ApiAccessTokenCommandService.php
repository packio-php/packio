<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Cake\Chronos\Chronos;
use Common\Exception\PublicValidationException;
use Exception;
use Packio\Identity\Application\Result\ApiAccessTokenRegeneration;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Result\IssuedApiAccessToken;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Application\Security\ApiAccessTokenScopes;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function in_array;
use function random_bytes;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final readonly class ApiAccessTokenCommandService
{
    /**
     * @param array{prefix?: string} $tokenConfig
     */
    public function __construct(
        private ApiAccessTokenRepositoryInterface $apiAccessTokenRepository,
        private ApiAccessTokenHasher $apiAccessTokenHasher,
        private ObjectMapperInterface $objectMapper,
        private UserRepositoryInterface $userRepository,
        private array $tokenConfig = [],
    ) {}

    /**
     * @param list<string> $scopes
     */
    public function issueForUser(string $userId, string $name, array $scopes, ?string $expiresAt = null): IssuedApiAccessToken
    {
        $scopes = $this->scopesAllowedForUser($userId, $this->normalizeScopes($scopes));

        // Validated after the admin filter so privileged scopes dropped for regular users stay silently dropped.
        foreach ($scopes as $scope) {
            if (! ApiAccessTokenScopes::isKnown($scope)) {
                throw new PublicValidationException('Unknown scope: ' . $scope . '.');
            }
        }

        $userId = trim($userId);
        $name   = trim($name);

        if ('' === $userId) {
            throw new PublicValidationException('User id is required.');
        }

        if ('' === $name) {
            throw new PublicValidationException('Token name is required.');
        }

        $plainTextToken = $this->prefix() . bin2hex(random_bytes(32));
        $entity         = ApiAccessToken::issue(
            userId: $userId,
            name: $name,
            tokenHash: $this->apiAccessTokenHasher->hash($plainTextToken),
            tokenPrefix: $this->tokenPrefix($plainTextToken),
            scopes: $this->normalizeScopes($scopes),
            expiresAt: $this->expiresAt($expiresAt),
        );

        $entity = $this->apiAccessTokenRepository->save($entity);

        return new IssuedApiAccessToken(
            token: $this->objectMapper->map($entity, ApiAccessTokenResult::class),
            plainTextToken: $plainTextToken,
        );
    }

    public function revokeForUser(string $userId, string $tokenId): bool
    {
        $entity = $this->apiAccessTokenRepository->findByIdForUser(trim($tokenId), trim($userId));
        if (! $entity instanceof ApiAccessToken) {
            return false;
        }

        $entity->revoke(new Chronos());
        $this->apiAccessTokenRepository->save($entity);

        return true;
    }

    /**
     * Atomically regenerates an active API access token: loads and revalidates
     * the token inside the repository transaction (advisory lock + FOR UPDATE),
     * issues a replacement carrying the same name, scopes and expiry, then
     * revokes the old token. A failure on either side leaves the previous
     * token intact.
     */
    public function regenerateForUser(string $userId, string $tokenId): ApiAccessTokenRegeneration
    {
        $userId = trim($userId);

        if ('' === $userId) {
            throw new PublicValidationException('User id is required.');
        }

        // The plain text token and its hash are prepared up front; the actual
        // replacement entity is built from the locked previous token inside the
        // transaction, so name/scopes/expiry always come from the revalidated row.
        $plainTextToken = $this->prefix() . bin2hex(random_bytes(32));

        $apiAccessTokenRegenerationSpec = new ApiAccessTokenRegenerationSpec(
            fn (ApiAccessToken $apiAccessToken): ApiAccessToken => ApiAccessToken::issue(
                userId: $userId,
                name: $apiAccessToken->name(),
                tokenHash: $this->apiAccessTokenHasher->hash($plainTextToken),
                tokenPrefix: $this->tokenPrefix($plainTextToken),
                scopes: $apiAccessToken->scopes(),
                expiresAt: $apiAccessToken->expiresAt(),
            ),
        );

        try {
            $issued = $this->apiAccessTokenRepository->regenerate($userId, trim($tokenId), $apiAccessTokenRegenerationSpec);
        } catch (RepositoryPersistException) {
            return ApiAccessTokenRegeneration::persistenceFailed();
        }

        if (! $issued instanceof ApiAccessTokenRegenerationOutcome) {
            return ApiAccessTokenRegeneration::notFound();
        }

        return ApiAccessTokenRegeneration::regenerated(
            new IssuedApiAccessToken(
                token: $this->objectMapper->map($issued->replacement, ApiAccessTokenResult::class),
                plainTextToken: $plainTextToken,
            ),
            $issued->revoked->getIdentifier()->toString(),
        );
    }

    public function deleteRevokedForUser(string $userId, string $tokenId): bool
    {
        $entity = $this->apiAccessTokenRepository->findByIdForUser(trim($tokenId), trim($userId));
        if (! $entity instanceof ApiAccessToken || ! $entity->isRevoked()) {
            return false;
        }

        $this->apiAccessTokenRepository->deleteAndFlush($entity);

        return true;
    }

    private function prefix(): string
    {
        $prefix = $this->tokenConfig['prefix'] ?? 'packio_api_';

        return '' !== trim($prefix) ? trim($prefix) : 'packio_api_';
    }

    private function tokenPrefix(string $plainTextToken): string
    {
        $visibleLength = strlen($this->prefix()) + 8;

        return substr($plainTextToken, 0, $visibleLength);
    }

    /**
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = array_map(trim(...), $scopes)
            |> (fn ($x) => array_filter($x, static fn (string $scope): bool => '' !== $scope))
            |> array_unique(...)
            |> array_values(...);

        return [] !== $normalized ? $normalized : ['read'];
    }

    /**
     * Only admins may hold privileged admin scopes; anyone else has them dropped.
     *
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    private function scopesAllowedForUser(string $userId, array $scopes): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user instanceof User && in_array('admin', $user->roles(), true)) {
            return $scopes;
        }

        $filtered = array_values(array_filter(
            $scopes,
            static fn (string $scope): bool => ! self::isPrivilegedAdminScope($scope),
        ));

        return [] !== $filtered ? $filtered : ['read'];
    }

    private static function isPrivilegedAdminScope(string $scope): bool
    {
        $scope = trim($scope);

        return '*' === $scope || 'admin' === $scope || str_starts_with($scope, 'admin:');
    }

    private function expiresAt(?string $expiresAt): ?Chronos
    {
        $expiresAt = trim((string) $expiresAt);
        if ('' === $expiresAt) {
            return null;
        }

        try {
            return new Chronos($expiresAt);
        } catch (Exception $exception) {
            throw new PublicValidationException('Expiration date is invalid.', 0, $exception);
        }
    }
}
