<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationResourcesHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceActivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeactivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeleteHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstancesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Rest\Infrastructure\Handler\SourceSyncHandler;
use Packio\Rest\Infrastructure\Request\CreateSourceProviderInstanceRequest;
use Packio\Rest\Infrastructure\Request\UpdateSourceProviderInstanceRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function json_encode;

final class TypedErrorHandlerTest extends TestCase
{
    /**
     * @param class-string<RequestHandlerInterface> $handlerClass
     * @param non-empty-string                      $method
     */
    #[DataProvider('instanceHandlers')]
    public function testUnexpectedInstanceFailuresRemainPrivateWithOptionalLogging(
        string $handlerClass,
        string $method,
        bool $withLogger
    ): void {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method($method)->willThrowException(new RuntimeException('https://user:private-password@host?token=private-token not found'));
        $logger  = $withLogger ? $this->safeLogger() : null;
        $handler = new $handlerClass($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $logger);
        $payload = [
            'provider_type' => 'gitlab_self_hosted',
            'label'         => 'GitLab',
            'base_url'      => 'https://gitlab.test',
            'auth_mode'     => 'token',
        ];
        $serverRequest = $this->request()
            ->withAttribute('instanceId', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d')
            ->withAttribute(CreateSourceProviderInstanceRequest::class, new CreateSourceProviderInstanceRequest($payload))
            ->withAttribute(UpdateSourceProviderInstanceRequest::class, new UpdateSourceProviderInstanceRequest($payload))
        ;
        $response = $handler->handle($serverRequest);
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('internal_error', (string) $response->getBody());
        self::assertStringNotContainsString('private-', (string) $response->getBody());
    }

    /** @return iterable<string, array{class-string<RequestHandlerInterface>, non-empty-string, bool}> */
    public static function instanceHandlers(): iterable
    {
        $handlers = [
            'create'     => [SourceProviderInstancesCreateHandler::class, 'createSourceProviderInstanceInContext'],
            'update'     => [SourceProviderInstanceUpdateHandler::class, 'updateSourceProviderInstanceInContext'],
            'activate'   => [SourceProviderInstanceActivateHandler::class, 'activateSourceProviderInstanceInContext'],
            'deactivate' => [SourceProviderInstanceDeactivateHandler::class, 'deactivateSourceProviderInstanceInContext'],
            'delete'     => [SourceProviderInstanceDeleteHandler::class, 'deleteSourceProviderInstanceInContext'],
        ];

        foreach ($handlers as $name => [$handler, $method]) {
            yield $name . ' with logger' => [$handler, $method, true];

            yield $name . ' without logger' => [$handler, $method, false];
        }
    }

    #[DataProvider('providerFailures')]
    public function testProviderFailuresDoNotExposeCause(Throwable $throwable, int $status, string $code): void
    {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method('providerResources')->willThrowException($throwable);
        if (401 === $status) {
            $facade->expects(self::once())->method('findProviderAuthorizationInOrganization')->with('0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d', 'org-1')->willReturn(new ProviderAuthorizationDto(
                id: '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d',
                organizationId: 'org-1',
                provider: 'github',
                authStrategy: 'oauth2',
                status: 'invalid',
                authFailureReason: 'token=private-token',
            ));
        }

        $providerAuthorizationResourcesHandler = new ProviderAuthorizationResourcesHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $this->safeLogger());
        $response                              = $providerAuthorizationResourcesHandler->handle($this->request()->withAttribute('authorizationId', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d')->withQueryParams([
            'provider' => 'github',
        ]));
        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString($code, (string) $response->getBody());
        self::assertStringNotContainsString('private-', (string) $response->getBody());
    }

    /** @return iterable<string, array{Throwable, int, string}> */
    public static function providerFailures(): iterable
    {
        $runtimeException = new RuntimeException('https://user:private-password@host?token=private-token');

        yield 'authentication' => [new ProviderAuthenticationException($runtimeException->getMessage(), 0, $runtimeException), 401, 'provider_authentication_failed'];

        yield 'unknown' => [$runtimeException, 500, 'internal_error'];
        foreach ([ProviderFailure::AccessDenied, ProviderFailure::RateLimited, ProviderFailure::Unavailable, ProviderFailure::InvalidResponse] as $failure) {
            yield $failure->value => [
                new ProviderCallException($failure, $runtimeException),
                match ($failure) {
                    ProviderFailure::AccessDenied => 403,
                    ProviderFailure::RateLimited  => 429,
                    default                       => 502,
                },
                $failure->value,
            ];
        }
    }

    public function testSyncFailureDoesNotExposeReason(): void
    {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method('findSource')->willReturn(SourceDto::fromArray([
            'id'              => '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d',
            'organization_id' => 'org-1',
        ]));
        $facade->expects(self::once())->method('scheduleSourceSync')->willThrowException(new RuntimeException('token=private-token'));
        $sourceSyncHandler = new SourceSyncHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $this->safeLogger());
        $response          = $sourceSyncHandler->handle($this->request()->withAttribute('sourceId', '0192f0c6-1c2d-7a3b-8c4d-5e6f7a8b9c0d'));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('reason', (string) $response->getBody());
        self::assertStringNotContainsString('private-', (string) $response->getBody());
    }

    private function request(): ServerRequest
    {
        return new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'org', 'Organization'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute('request_id', 'request-1')
        ;
    }

    private function safeLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::isString(), self::callback(static function(array $context): bool {
            self::assertSame('request-1', $context['correlation_id']);
            self::assertStringNotContainsString('private-', json_encode($context, JSON_THROW_ON_ERROR));
            self::assertArrayHasKey('exception', $context);

            return true;
        }));

        return $logger;
    }
}
