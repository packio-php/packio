<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Command;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceCommandService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceCommandServiceProviderAuthorizationTest extends TestCase
{
    use InertSourceCollaborators;

    #[Test]
    public function forgejoSelfHostedTokenStrategyIsNormalizedByConvention(): void
    {
        $resolver = new SourceAuthorizationStrategyResolver();

        self::assertSame('forgejo_self_hosted_token', $resolver->defaultFor('forgejo_self_hosted'));
        self::assertSame('forgejo_self_hosted_token', $resolver->normalize('forgejo_self_hosted', ' token '));
        self::assertSame('oauth2', $resolver->normalize('forgejo_self_hosted', 'oauth2'));
    }

    #[Test]
    public function reassigningAuthorizationToItselfIsRejected(): void
    {
        $sourceRepository         = $this->createMock(SourceRepositoryInterface::class);
        $authorizationRepository  = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $sourceRepository->expects(self::never())->method('save');
        $authorizationRepository->expects(self::never())->method('save');
        $authorizationRepository->expects(self::never())->method('deleteAndFlush');
        $authorizationRepository->expects(self::never())->method('findByIdInOrganization');

        $providerAuthorizationSecrets = new ProviderAuthorizationSecrets($this->createMock(SecretEncryptorInterface::class));
        $sourceCommandService         = new SourceCommandService(
            $sourceRepository,
            $authorizationRepository,
            $this->createMock(SourceProviderRegistryInterface::class),
            new ProviderAuthorizationFactory($providerAuthorizationSecrets),
            $providerAuthorizationSecrets,
            new SourceAuthorizationStrategyResolver(),
            new SourceMaintenanceService($sourceRepository),
            $this->createMock(SourceOAuthAccountOwnershipVerifierInterface::class),
            $this->createMock(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceAuthorizationInvalidationService(),
            $this->inertWebhookAutoRegistrationService(),
            new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );

        self::assertSame(
            [
                'success' => false,
                'error'   => 'Choose a different authorization.',
            ],
            $sourceCommandService->reassignAndDeleteProviderAuthorization('auth-1', 'org-1', 'auth-1'),
        );
    }
}
