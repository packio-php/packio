<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Mapper;

use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;

use function is_string;
use function strlen;
use function substr;

final readonly class ProviderAuthorizationDtoMapper
{
    public function __construct(
        private SecretResolverInterface $secretResolver,
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository
    ) {}

    public function map(ProviderAuthorization $providerAuthorization): ProviderAuthorizationDto
    {
        $oauthAccountId = $providerAuthorization->sourceOAuthAccountId();
        $oauthAccount   = null;
        if (null !== $oauthAccountId && '' !== $oauthAccountId) {
            $oauthAccount = $this->sourceOAuthAccountRepository->findById($oauthAccountId);
        }

        $maskedToken            = $this->resolveMaskedValue($providerAuthorization, $oauthAccount);
        $maskedValue            = $maskedToken['masked_value'];
        $tokenAuthFailureReason = $maskedToken['auth_failure_reason'];

        $authHealth         = null;
        $authFailureReason  = null;
        $lastTokenRefreshAt = null;

        if ('active' !== $providerAuthorization->status()) {
            $authHealth        = 'reauthorization_required';
            $authFailureReason = 'Provider authorization is inactive. Please reconnect your provider.';
        }

        if (null !== $oauthAccountId && '' !== $oauthAccountId) {
            if ($oauthAccount instanceof SourceOAuthAccount) {
                if (null !== $tokenAuthFailureReason) {
                    $authHealth = 'reauthorization_required';
                    $authFailureReason ??= $tokenAuthFailureReason;
                } elseif ($oauthAccount->isActive()) {
                    $authHealth ??= 'healthy';
                } else {
                    $authHealth        = 'reauthorization_required';
                    $authFailureReason = 'OAuth account is inactive. Please reconnect your provider.';
                }
                $lastTokenRefreshAt = $oauthAccount->getUpdatedAt()?->format(DATE_ATOM);
            } else {
                $authHealth        = 'reauthorization_required';
                $authFailureReason = 'Linked OAuth account not found. Please reconnect your provider.';
            }
        }

        return new ProviderAuthorizationDto(
            id: $providerAuthorization->getIdentifier()->toString(),
            organizationId: $providerAuthorization->organizationId(),
            provider: $providerAuthorization->provider(),
            authStrategy: $providerAuthorization->authStrategy(),
            status: $providerAuthorization->status(),
            accountName: $providerAuthorization->accountName(),
            configured: 'active' === $providerAuthorization->status() && [] !== $providerAuthorization->configuration(),
            maskedValue: $maskedValue,
            sourceOAuthAccountId: '' !== ($oauthAccountId ?? '') ? $oauthAccountId : null,
            providerInstanceId: $providerAuthorization->providerInstanceId(),
            authHealth: $authHealth,
            authFailureReason: $authFailureReason,
            lastTokenRefreshAt: $lastTokenRefreshAt,
        );
    }

    /**
     * @return array{masked_value: string, auth_failure_reason: ?string}
     */
    private function resolveMaskedValue(ProviderAuthorization $providerAuthorization, ?SourceOAuthAccount $sourceOAuthAccount): array
    {
        try {
            $token = $this->secretResolver->resolveAuthorizationEntitySecret($providerAuthorization, $sourceOAuthAccount);
        } catch (
            SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException
        ) {
            return [
                'masked_value'        => '',
                'auth_failure_reason' => 'OAuth account token is unavailable. Please reconnect your provider.',
            ];
        }

        if (is_string($token) && strlen($token) >= 4) {
            return [
                'masked_value'        => '****' . substr($token, -4),
                'auth_failure_reason' => null,
            ];
        }

        return [
            'masked_value'        => '',
            'auth_failure_reason' => null,
        ];
    }
}
