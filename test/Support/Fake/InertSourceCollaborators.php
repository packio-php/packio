<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;

/**
 * Required Source collaborators that do nothing: stub repositories find nothing
 * and the webhook registrar registry supports no provider.
 */
trait InertSourceCollaborators
{
    private function inertSourceProviderInstanceOAuthConfig(): SourceProviderInstanceOAuthConfig
    {
        return new SourceProviderInstanceOAuthConfig(
            new SourceProviderInstanceSecrets($this->createStub(SecretEncryptorInterface::class)),
            new BrandingConfig(),
        );
    }

    private function inertSourceAuthorizationInvalidationService(
        ?SourceRepositoryInterface $sourceRepository = null
    ): SourceAuthorizationInvalidationService {
        return new SourceAuthorizationInvalidationService(
            $sourceRepository ?? $this->createStub(SourceRepositoryInterface::class),
            $this->createStub(ProviderAuthorizationRepositoryInterface::class),
            new SourceOAuthService(
                $this->createStub(OAuthProviderPort::class),
                $this->createStub(SourceOAuthAccountRepositoryInterface::class),
                new SourceOAuthAccountSecrets($this->createStub(SecretEncryptorInterface::class)),
                $this->createStub(OrganizationQueryPort::class),
                $this->createStub(SourceProviderInstanceRepositoryInterface::class),
                $this->inertSourceProviderInstanceOAuthConfig(),
            ),
        );
    }

    private function inertWebhookAutoRegistrationService(): WebhookAutoRegistrationService
    {
        return new WebhookAutoRegistrationService(
            $this->createStub(WebhookRegistrarRegistryInterface::class),
            $this->createStub(SourceRepositoryInterface::class),
            $this->inertSourceAuthorizationInvalidationService(),
        );
    }
}
