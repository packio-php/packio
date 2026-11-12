<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Common\Security\StoredSecretResolver;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceConnectAuthorizationResolver;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class SourceConnectAuthorizationResolverTest extends TestCase
{
    use InertSourceCollaborators;

    public function testReusesMatchingEncryptedAuthorization(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize('org', 'github', 'github_token', [
            'token' => 'encrypted',
        ]);
        $providerAuthorization->setIdentifier(Uuid::uuid7());
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->expects(self::once())->method('findByOrganization')->with('org', 'github')->willReturn([$providerAuthorization]);
        $repository->expects(self::never())->method('save');
        $encryptor = $this->createMock(SecretEncryptorInterface::class);
        $encryptor->expects(self::once())->method('isEncrypted')->with('encrypted')->willReturn(true);
        $encryptor->expects(self::once())->method('decrypt')->with('encrypted')->willReturn('secret');
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $encryptor);
        self::assertSame($providerAuthorization->getIdentifier()->toString(), $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'github', '', [
            'token' => 'secret',
        ], ''));
    }

    public function testCreatesAuthorizationWhenCredentialDoesNotMatch(): void
    {
        $uuid       = Uuid::uuid7();
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->method('findByOrganization')->willReturn([
            ProviderAuthorization::authorize('org', 'github', 'github_token', [
                'token' => 'other',
            ])]);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function(ProviderAuthorization $providerAuthorization) use ($uuid): ProviderAuthorization {
            self::assertSame('org', $providerAuthorization->organizationId());
            self::assertSame('github', $providerAuthorization->provider());
            self::assertSame('github_token', $providerAuthorization->authStrategy());
            self::assertSame('encrypted:secret', $providerAuthorization->secret());
            $providerAuthorization->setIdentifier($uuid);

            return $providerAuthorization;
        });
        $secretEncryptor = $this->createStub(SecretEncryptorInterface::class);
        $secretEncryptor->method('encrypt')->willReturnCallback(static fn (string $plaintext): string => 'encrypted:' . $plaintext);
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $secretEncryptor);
        self::assertSame($uuid->toString(), $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'github', '', [
            'token' => 'secret',
        ], ''));
    }

    public function testReusesMatchingTokenOnSameProviderInstance(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize('org', 'github', 'github_token', [
            'token' => 'secret',
        ]);
        $providerAuthorization->assignProviderInstance('instance-1');
        $providerAuthorization->setIdentifier(Uuid::uuid7());
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->method('findByOrganization')->willReturn([$providerAuthorization]);
        $repository->expects(self::never())->method('save');
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $this->createStub(SecretEncryptorInterface::class));
        self::assertSame($providerAuthorization->getIdentifier()->toString(), $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'github', '', [
            'token' => 'secret',
        ], 'instance-1'));
    }

    #[DataProvider('nonReusableTokenAuthorizations')]
    public function testCreatesAuthorizationWhenMatchingTokenIsNotReusable(
        string $storedStrategy,
        ?string $storedInstance,
        string $requestedInstance
    ): void {
        $uuid                  = Uuid::uuid7();
        $providerAuthorization = ProviderAuthorization::authorize('org', 'github', $storedStrategy, [
            'token' => 'secret',
        ]);
        $providerAuthorization->assignProviderInstance($storedInstance);
        $providerAuthorization->setIdentifier(Uuid::uuid7());
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->method('findByOrganization')->willReturn([$providerAuthorization]);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function(ProviderAuthorization $providerAuthorization) use ($uuid): ProviderAuthorization {
            $providerAuthorization->setIdentifier($uuid);

            return $providerAuthorization;
        });
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $this->createStub(SecretEncryptorInterface::class));
        self::assertSame($uuid->toString(), $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'github', '', [
            'token' => 'secret',
        ], $requestedInstance));
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function nonReusableTokenAuthorizations(): iterable
    {
        yield 'other provider instance' => ['github_token', 'instance-1', 'instance-2'];

        yield 'non-default strategy' => ['github_oauth', null, ''];
    }

    public function testRejectsAuthorizationOutsideOrganization(): void
    {
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->expects(self::once())->method('findByIdInOrganization')->with('foreign-auth', 'org')->willReturn(null);
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $this->createStub(SecretEncryptorInterface::class));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider authorization is unavailable.');
        $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'github', 'foreign-auth', [], '');
    }

    public function testReusesMatchingActiveAuthorizationId(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize('org', 'gitlab_self_hosted', 'gitlab_token', []);
        $providerAuthorization->assignProviderInstance('instance-1');
        $repository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $repository->expects(self::once())->method('findByIdInOrganization')->with('auth-1', 'org')->willReturn($providerAuthorization);
        $repository->expects(self::never())->method('save');
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $this->createStub(SecretEncryptorInterface::class));
        self::assertSame('auth-1', $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'gitlab_self_hosted', 'auth-1', [], 'instance-1'));
    }

    #[DataProvider('unavailableAuthorizations')]
    public function testRejectsUnavailableAuthorizationId(
        string $storedProvider,
        string $status,
        ?string $storedInstance,
        string $requestedInstance
    ): void {
        $providerAuthorization = ProviderAuthorization::authorize('org', $storedProvider, 'gitlab_token', []);
        $providerAuthorization->updateStatus($status);
        $providerAuthorization->assignProviderInstance($storedInstance);
        $repository = $this->createStub(ProviderAuthorizationRepositoryInterface::class);
        $repository->method('findByIdInOrganization')->willReturn($providerAuthorization);
        $sourceConnectAuthorizationResolver = $this->resolver($repository, $this->createStub(SecretEncryptorInterface::class));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider authorization is unavailable.');
        $sourceConnectAuthorizationResolver->resolve(new ActorContext('org', 'user', 'owner'), 'gitlab_self_hosted', 'auth-1', [], $requestedInstance);
    }

    /**
     * @return iterable<string, array{string, string, ?string, string}>
     */
    public static function unavailableAuthorizations(): iterable
    {
        yield 'other provider' => ['github', 'active', 'instance-1', 'instance-1'];

        yield 'inactive' => ['gitlab_self_hosted', 'inactive', 'instance-1', 'instance-1'];

        yield 'other instance' => ['gitlab_self_hosted', 'active', 'instance-2', 'instance-1'];
    }

    private function resolver(
        ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        SecretEncryptorInterface $secretEncryptor
    ): SourceConnectAuthorizationResolver {
        $providerAuthorizationSecrets = new ProviderAuthorizationSecrets($secretEncryptor);
        $sourceRepository             = $this->createStub(SourceRepositoryInterface::class);

        return new SourceConnectAuthorizationResolver(
            new SourceCommandService(
                $sourceRepository,
                $providerAuthorizationRepository,
                $this->createStub(SourceProviderRegistryInterface::class),
                new ProviderAuthorizationFactory($providerAuthorizationSecrets),
                $providerAuthorizationSecrets,
                new SourceAuthorizationStrategyResolver(),
                new SourceMaintenanceService($sourceRepository),
                $this->createStub(SourceOAuthAccountOwnershipVerifierInterface::class),
                $this->createStub(SourceProviderInstanceRepositoryInterface::class),
                $this->inertSourceAuthorizationInvalidationService(),
                $this->inertWebhookAutoRegistrationService(),
                new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
            ),
            new SourceAuthorizationStrategyResolver(),
            $providerAuthorizationRepository,
            new StoredSecretResolver($secretEncryptor),
        );
    }
}
