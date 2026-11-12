<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Application\Service;

use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

#[CoversClass(WebhookAutoRegistrationService::class)]
#[AllowMockObjectsWithoutExpectations]
final class WebhookAutoRegistrationServiceTest extends TestCase
{
    use InertSourceCollaborators;

    private const string SOURCE_ID   = '550e8400-e29b-41d4-a716-446655440000';
    private const string PROVIDER    = 'github';
    private const string EXTERNAL_ID = '123456';

    private MockObject&WebhookRegistrarRegistryInterface $registry;
    private MockObject&SourceRepositoryInterface $sourceRepository;
    private WebhookAutoRegistrationService $webhookAutoRegistrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry                       = $this->createMock(WebhookRegistrarRegistryInterface::class);
        $this->sourceRepository               = $this->createMock(SourceRepositoryInterface::class);
        $this->webhookAutoRegistrationService = new WebhookAutoRegistrationService(
            $this->registry,
            $this->sourceRepository,
            $this->inertSourceAuthorizationInvalidationService(),
        );
    }

    public function testSupportsReturnsTrueWhenRegistryHasProvider(): void
    {
        $this->registry
            ->expects(self::once())
            ->method('hasProvider')
            ->with('github')
            ->willReturn(true)
        ;

        self::assertTrue($this->webhookAutoRegistrationService->supports('github'));
    }

    public function testSupportsReturnsFalseWhenRegistryDoesNotHaveProvider(): void
    {
        $this->registry
            ->expects(self::once())
            ->method('hasProvider')
            ->with('unknown')
            ->willReturn(false)
        ;

        self::assertFalse($this->webhookAutoRegistrationService->supports('unknown'));
    }

    public function testRegisterReturnsFailureWhenRegistrarFails(): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createMock(WebhookRegistrarInterface::class);

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn($registrar)
        ;

        $registrar
            ->expects(self::once())
            ->method('register')
            ->with($source)
            ->willReturn(new WebhookRegistrationResult(
                success: false,
                externalId: '',
                message: 'Upstream API error',
                alreadyExists: false,
                authHealth: 'reauthorization_required',
                authFailureReason: 'Token rejected',
            ))
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame('Upstream API error', $webhookRegistrationResult->message);
        self::assertSame('reauthorization_required', $webhookRegistrationResult->authHealth);
        self::assertSame('Token rejected', $webhookRegistrationResult->authFailureReason);
    }

    public function testRegisterReturnsSuccessAndPersistsExternalId(): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createMock(WebhookRegistrarInterface::class);

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn($registrar)
        ;

        $registrar
            ->expects(self::once())
            ->method('register')
            ->with($source)
            ->willReturn(new WebhookRegistrationResult(
                success: true,
                externalId: self::EXTERNAL_ID,
                message: 'Webhook registered successfully.',
                alreadyExists: false,
            ))
        ;

        $uuid = $this->createUuidMock(self::SOURCE_ID);

        $source
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn($uuid)
        ;

        $source
            ->expects(self::any())
            ->method('providerConfig')
            ->willReturn([])
        ;

        $source
            ->expects(self::once())
            ->method('replaceProviderConfig')
            ->with(self::arrayHasKey('webhook_external_id'))
        ;

        $this->sourceRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SOURCE_ID)
            ->willReturn($source)
        ;

        $this->sourceRepository
            ->expects(self::once())
            ->method('save')
            ->with($source)
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertTrue($webhookRegistrationResult->success);
        self::assertFalse($webhookRegistrationResult->alreadyExists);
        self::assertSame(self::EXTERNAL_ID, $webhookRegistrationResult->externalId);
    }

    public function testRegisterReturnsAlreadyExistsWhenRegistrarFindsExisting(): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createMock(WebhookRegistrarInterface::class);

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn($registrar)
        ;

        $registrar
            ->expects(self::once())
            ->method('register')
            ->with($source)
            ->willReturn(new WebhookRegistrationResult(
                success: true,
                externalId: self::EXTERNAL_ID,
                message: 'Webhook already registered.',
                alreadyExists: true,
            ))
        ;

        $uuid = $this->createUuidMock(self::SOURCE_ID);

        $source
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn($uuid)
        ;

        $source
            ->expects(self::any())
            ->method('providerConfig')
            ->willReturn([])
        ;

        $source
            ->expects(self::once())
            ->method('replaceProviderConfig')
            ->with(self::arrayHasKey('webhook_external_id'))
        ;

        $this->sourceRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::SOURCE_ID)
            ->willReturn($source)
        ;

        $this->sourceRepository
            ->expects(self::once())
            ->method('save')
            ->with($source)
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertTrue($webhookRegistrationResult->success);
        self::assertTrue($webhookRegistrationResult->alreadyExists);
        self::assertSame(self::EXTERNAL_ID, $webhookRegistrationResult->externalId);
        self::assertSame('Webhook already registered.', $webhookRegistrationResult->message);
    }

    public function testRegisterReturnsFailureWhenNoRegistrarForProvider(): void
    {
        $source = $this->createSourceMock();

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn(null)
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertFalse($webhookRegistrationResult->success);
        self::assertStringContainsString('not supported', $webhookRegistrationResult->message);
    }

    public function testRegisterReturnsFailureWhenRegistrarThrows(): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createMock(WebhookRegistrarInterface::class);

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn($registrar)
        ;

        $registrar
            ->expects(self::once())
            ->method('register')
            ->with($source)
            ->willThrowException(new RuntimeException('Connection timeout'))
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertFalse($webhookRegistrationResult->success);
        self::assertStringContainsString('Connection timeout', $webhookRegistrationResult->message);
    }

    public function testRegisterMapsOAuthTokenFailureToReauthorizationRequired(): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createMock(WebhookRegistrarInterface::class);

        $this->registry
            ->expects(self::once())
            ->method('forProvider')
            ->with(self::PROVIDER)
            ->willReturn($registrar)
        ;

        $registrar
            ->expects(self::once())
            ->method('register')
            ->with($source)
            ->willThrowException(new SourceTokenRefreshUnavailableException('OAuth account is inactive. Reauthorization required.'))
        ;

        $webhookRegistrationResult = $this->webhookAutoRegistrationService->register($source);

        self::assertInstanceOf(WebhookRegistrationResult::class, $webhookRegistrationResult);
        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame('Provider authorization is invalid or expired. Please reconnect.', $webhookRegistrationResult->message);
        self::assertSame('reauthorization_required', $webhookRegistrationResult->authHealth);
        self::assertSame('OAuth account is inactive. Reauthorization required.', $webhookRegistrationResult->authFailureReason);
    }

    #[DataProvider('refreshFailures')]
    public function testRegisterMarksTheAuthorizationInvalidOnlyWhenTheRefreshWasRejected(bool $requiresReauthorization): void
    {
        $source    = $this->createSourceMock();
        $registrar = $this->createStub(WebhookRegistrarInterface::class);
        $registrar->method('register')->willThrowException(
            new SourceTokenRefreshFailedException('Token refresh failed', requiresReauthorization: $requiresReauthorization),
        );
        $registry = $this->createStub(WebhookRegistrarRegistryInterface::class);
        $registry->method('forProvider')->willReturn($registrar);

        // markInvalidForSource starts by loading the source.
        $invalidationSources = $this->createMock(SourceRepositoryInterface::class);
        $invalidationSources->expects($requiresReauthorization ? self::once() : self::never())->method('findById')->willReturn(null);
        $webhookAutoRegistrationService = new WebhookAutoRegistrationService(
            $registry,
            $this->createStub(SourceRepositoryInterface::class),
            $this->inertSourceAuthorizationInvalidationService($invalidationSources),
        );

        $webhookRegistrationResult = $webhookAutoRegistrationService->register($source);

        self::assertFalse($webhookRegistrationResult->success);
        self::assertSame($requiresReauthorization ? 'reauthorization_required' : null, $webhookRegistrationResult->authHealth);
    }

    /** @return array<string, array{bool}> */
    public static function refreshFailures(): array
    {
        return [
            'rejected'  => [true],
            'transient' => [false],
        ];
    }

    private function createUuidMock(string $uuidString): UuidInterface
    {
        return Uuid::fromString($uuidString);
    }

    /**
     * @return MockObject&Source
     */
    private function createSourceMock(): Source
    {
        $source = $this->createMock(Source::class);
        $source
            ->expects(self::any())
            ->method('provider')
            ->willReturn(self::PROVIDER)
        ;
        $source->method('getIdentifier')->willReturn(Uuid::fromString(self::SOURCE_ID));

        return $source;
    }
}
