<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Common\Branding\BrandingConfig;
use Common\Registry\RegistryProtocolPermissions;
use Packio\Identity\Application\Result\IssuedRegistryAccessToken;
use Packio\Identity\Application\Result\RegistryAccessTokenRegeneration;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function array_filter;
use function array_values;
use function bin2hex;
use function hash;
use function in_array;
use function random_bytes;
use function strlen;
use function substr;
use function trim;

final readonly class RegistryTokenCommandService
{
    public function __construct(
        private RegistryAccessTokenRepositoryInterface $registryAccessTokenRepository,
        private BrandingConfig $brandingConfig,
        private RegistryAccessTokenSecrets $registryAccessTokenSecrets
    ) {}

    /**
     * @param list<string> $permissions
     */
    public function issueForOrganizationUser(
        string $organizationId,
        string $userId,
        string $name,
        array $permissions = [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE]
    ): IssuedRegistryAccessToken {
        $plainTextToken
            = $this->brandingConfig->registryTokenPrefix() . bin2hex(random_bytes(24));

        $maskedToken    = $this->maskToken($plainTextToken);
        $encryptedToken = $this->registryAccessTokenSecrets->encryptToken($plainTextToken);

        $registryAccessToken = $this->registryAccessTokenRepository->save(
            RegistryAccessToken::issue(
                $organizationId,
                $userId,
                trim($name),
                hash('sha256', $plainTextToken),
                $maskedToken,
                $this->normalizePermissions($permissions),
                $encryptedToken,
            ),
        );

        return new IssuedRegistryAccessToken(
            id: $registryAccessToken->getIdentifier()->toString(),
            name: $registryAccessToken->name(),
            plainTextToken: $plainTextToken,
            maskedToken: $maskedToken,
            permissions: $registryAccessToken->permissions(),
            createdAt: $registryAccessToken->getCreatedAt()->format(DATE_ATOM),
        );
    }

    public function regenerateForOrganizationUser(string $organizationId, string $userId, string $tokenId): RegistryAccessTokenRegeneration
    {
        $organizationId = trim($organizationId);
        $userId         = trim($userId);
        $tokenId        = trim($tokenId);
        if (in_array('', [$organizationId, $userId, $tokenId], true)) {
            return RegistryAccessTokenRegeneration::notFound();
        }

        $plainTextToken                                = $this->brandingConfig->registryTokenPrefix() . bin2hex(random_bytes(24));
        $maskedToken                                   = $this->maskToken($plainTextToken);
        $encryptedToken                                = $this->registryAccessTokenSecrets->encryptToken($plainTextToken);
        $registryAccessTokenRegenerationSpec           = new RegistryAccessTokenRegenerationSpec(
            static fn (RegistryAccessToken $registryAccessToken): RegistryAccessToken => RegistryAccessToken::issue(
                $organizationId,
                $userId,
                $registryAccessToken->name(),
                hash('sha256', $plainTextToken),
                $maskedToken,
                $registryAccessToken->permissions(),
                $encryptedToken,
            ),
        );

        try {
            $outcome = $this->registryAccessTokenRepository->regenerate($organizationId, $userId, $tokenId, $registryAccessTokenRegenerationSpec);
        } catch (RepositoryPersistException) {
            return RegistryAccessTokenRegeneration::persistenceFailed();
        }

        if (! $outcome instanceof RegistryAccessTokenRegenerationOutcome) {
            return RegistryAccessTokenRegeneration::notFound();
        }

        $replacement = $outcome->replacement;

        return RegistryAccessTokenRegeneration::regenerated(new IssuedRegistryAccessToken(
            id: $replacement->getIdentifier()->toString(),
            name: $replacement->name(),
            plainTextToken: $plainTextToken,
            maskedToken: $replacement->maskedToken(),
            permissions: $replacement->permissions(),
            createdAt: $replacement->getCreatedAt()->format(DATE_ATOM),
        ), $outcome->revoked->getIdentifier()->toString());
    }

    public function revokeForOrganization(string $organizationId): int
    {
        $revoked = 0;

        foreach ($this->registryAccessTokenRepository->latestForOrganization($organizationId) as $registryAccessToken) {
            $this->registryAccessTokenRepository->deleteAndFlush($registryAccessToken);
            ++$revoked;
        }

        return $revoked;
    }

    public function revokeForOrganizationUser(string $id, string $organizationId, string $userId): bool
    {
        if ('' === trim($organizationId) || '' === trim($userId)) {
            return false;
        }

        $entity = $this->registryAccessTokenRepository->findByIdForOwnerInOrganization(
            $id,
            $userId,
            $organizationId,
        );
        if (! $entity instanceof RegistryAccessToken) {
            return false;
        }

        $this->registryAccessTokenRepository->deleteAndFlush($entity);

        return true;
    }

    /**
     * Mask a token for display (e.g., "rt_abc12345..." -> "rt_abc123...****").
     */
    private function maskToken(string $token): string
    {
        $prefixLength = strlen($this->brandingConfig->registryTokenPrefix());
        if (strlen($token) <= $prefixLength + 4) {
            return $token;
        }

        $visiblePart = substr($token, 0, $prefixLength + 8);
        $maskedPart  = substr($token, -4);

        return $visiblePart . '...' . $maskedPart;
    }

    /**
     * @param list<string> $permissions
     *
     * @return list<string>
     */
    private function normalizePermissions(array $permissions): array
    {
        return array_values(array_filter($permissions, static fn (string $permission): bool => '' !== trim($permission)));
    }
}
