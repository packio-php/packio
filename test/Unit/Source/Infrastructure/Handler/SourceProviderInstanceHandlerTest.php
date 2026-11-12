<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceDependenciesHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceInactiveAuthorizationDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstancesHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceStatusHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Source\Infrastructure\Request\SourceProviderInstanceRequest;
use Packio\Source\Infrastructure\Response\SourceProviderInstancePresenter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Delete;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Patch;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function json_decode;
use function json_encode;

#[CoversClass(SourceProviderInstancesHandler::class)]
#[CoversClass(SourceProviderInstanceStoreHandler::class)]
#[CoversClass(SourceProviderInstanceUpdateHandler::class)]
#[CoversClass(SourceProviderInstanceStatusHandler::class)]
#[CoversClass(SourceProviderInstanceDeleteHandler::class)]
#[CoversClass(SourceProviderInstanceDependenciesHandler::class)]
#[CoversClass(SourceProviderInstanceInactiveAuthorizationDeleteHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceProviderInstanceHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('handlerClasses')]
    public function ownerOnlyRoutesUseSourcesManagePermissionWithRequestContext(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('sources.manage', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function handlerClasses(): iterable
    {
        yield 'index' => [SourceProviderInstancesHandler::class];

        yield 'store' => [SourceProviderInstanceStoreHandler::class];

        yield 'update' => [SourceProviderInstanceUpdateHandler::class];

        yield 'status' => [SourceProviderInstanceStatusHandler::class];

        yield 'delete' => [SourceProviderInstanceDeleteHandler::class];

        yield 'dependencies' => [SourceProviderInstanceDependenciesHandler::class];

        yield 'inactive authorization cleanup' => [SourceProviderInstanceInactiveAuthorizationDeleteHandler::class];
    }

    #[Test]
    public function routesUseExpectedHttpMethodsWithoutLegacyManageGuard(): void
    {
        self::assertCount(1, new ReflectionClass(SourceProviderInstancesHandler::class)->getAttributes(Get::class));
        self::assertCount(1, new ReflectionClass(SourceProviderInstanceStoreHandler::class)->getAttributes(Post::class));
        self::assertCount(1, new ReflectionClass(SourceProviderInstanceUpdateHandler::class)->getAttributes(Patch::class));
        self::assertCount(2, new ReflectionClass(SourceProviderInstanceStatusHandler::class)->getAttributes(Post::class));
        self::assertCount(1, new ReflectionClass(SourceProviderInstanceDeleteHandler::class)->getAttributes(Delete::class));
        self::assertCount(1, new ReflectionClass(SourceProviderInstanceDependenciesHandler::class)->getAttributes(Get::class));
        self::assertCount(1, new ReflectionClass(SourceProviderInstanceInactiveAuthorizationDeleteHandler::class)->getAttributes(Post::class));

        foreach (self::handlerClasses() as [$handlerClass]) {
            $reflectionClass = new ReflectionClass($handlerClass);
            foreach ([$reflectionClass->getAttributes(Get::class), $reflectionClass->getAttributes(Post::class), $reflectionClass->getAttributes(Patch::class), $reflectionClass->getAttributes(Delete::class)] as $routeAttributes) {
                foreach ($routeAttributes as $routeAttribute) {
                    self::assertNull($routeAttribute->newInstance()->middleware);
                }
            }
        }
    }

    #[Test]
    public function indexReturnsInstancesWithoutClientSecret(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('sourceProviderInstances')
            ->with('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED)
            ->willReturn([
                new SourceProviderInstanceDto(
                    id: '11111111-1111-4111-8111-111111111111',
                    organizationId: 'org-1',
                    providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                    label: 'Company GitLab',
                    baseUrl: 'https://gitlab.company.test',
                    clientId: 'client-id',
                    hasClientSecret: true,
                    authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
                    status: SourceProviderInstance::STATUS_ACTIVE,
                ),
            ])
        ;

        $serverRequest = new ServerRequest([], [], '/organization/my-org/source-provider-instances', 'GET')
            ->withQueryParams([
                'provider_type' => SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            ])
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
        ;

        $response = new SourceProviderInstancesHandler($sourceApiFacade, new SourceProviderInstancePresenter())->handle($serverRequest);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('11111111-1111-4111-8111-111111111111', $body['instances'][0]['id'] ?? null);
        self::assertTrue($body['instances'][0]['has_client_secret'] ?? false);
        self::assertArrayNotHasKey('client_secret', $body['instances'][0]);
    }

    #[Test]
    public function dependenciesReturnSafeStructuredSummaries(): void
    {
        $actorContext    = new ActorContext('org-1', 'user-1', 'owner');
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('sourceProviderInstanceDependenciesInContext')
            ->with($actorContext, '11111111-1111-4111-8111-111111111111')
            ->willReturn(new SourceProviderInstanceDependenciesDto(
                instanceId: '11111111-1111-4111-8111-111111111111',
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                sources: [[
                    'id'       => 'source-1',
                    'name'     => 'Registry',
                    'url'      => 'https://gitlab.company.test/group/registry',
                    'provider' => SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                    'status'   => 'active',
                ]],
                authorizations: [[
                    'id'           => 'authorization-1',
                    'accountName'  => 'Former administrator',
                    'provider'     => SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                    'authStrategy' => 'oauth',
                    'status'       => 'inactive',
                ]],
            ))
        ;

        $response = new SourceProviderInstanceDependenciesHandler($sourceApiFacade, new SourceProviderInstancePresenter())->handle(
            new ServerRequest([], [], '/organization/my-org/source-provider-instances/instance-1/dependencies', 'GET')
                ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
                ->withAttribute('id', '11111111-1111-4111-8111-111111111111'),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Registry', $body['sources'][0]['name'] ?? null);
        self::assertSame('inactive', $body['authorizations'][0]['status'] ?? null);
        self::assertSame(1, $body['inactive_authorization_count'] ?? null);
        self::assertArrayNotHasKey('configuration', $body['authorizations'][0]);
        self::assertArrayNotHasKey('client_secret', $body['instance']);
    }

    #[Test]
    public function inactiveAuthorizationCleanupReturnsConflictDependencies(): void
    {
        $actorContext    = new ActorContext('org-1', 'user-1', 'owner');
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('deleteSourceProviderInstanceWithInactiveAuthorizationsInContext')
            ->with($actorContext, '11111111-1111-4111-8111-111111111111')
            ->willThrowException(SourceProviderInstanceException::inUse())
        ;
        $sourceApiFacade->expects(self::once())
            ->method('sourceProviderInstanceDependenciesInContext')
            ->with($actorContext, '11111111-1111-4111-8111-111111111111')
            ->willReturn(new SourceProviderInstanceDependenciesDto(
                instanceId: '11111111-1111-4111-8111-111111111111',
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                sources: [],
                authorizations: [[
                    'id'           => 'authorization-1',
                    'accountName'  => 'Current administrator',
                    'provider'     => SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                    'authStrategy' => 'oauth',
                    'status'       => 'active',
                ]],
            ))
        ;

        $response = new SourceProviderInstanceInactiveAuthorizationDeleteHandler($sourceApiFacade, new SourceProviderInstancePresenter())->handle(
            new ServerRequest([], [], '/organization/my-org/source-provider-instances/instance-1/delete-inactive-authorizations', 'POST')
                ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
                ->withAttribute('id', '11111111-1111-4111-8111-111111111111'),
        );

        self::assertSame(409, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['dependencies']['active_authorization_count'] ?? null);
        self::assertSame('Current administrator', $body['dependencies']['authorizations'][0]['account_name'] ?? null);
        self::assertArrayNotHasKey('configuration', $body['dependencies']['authorizations'][0]);
    }

    #[Test]
    public function inactiveAuthorizationCleanupReturnsDeletionCounts(): void
    {
        $actorContext    = new ActorContext('org-1', 'user-1', 'owner');
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('deleteSourceProviderInstanceWithInactiveAuthorizationsInContext')
            ->with($actorContext, '11111111-1111-4111-8111-111111111111')
            ->willReturn([
                'deletedInactiveAuthorizationsCount' => 3,
                'deletedSourceOAuthAccountsCount'    => 2,
            ])
        ;

        $response = new SourceProviderInstanceInactiveAuthorizationDeleteHandler($sourceApiFacade, new SourceProviderInstancePresenter())->handle(
            new ServerRequest([], [], '/organization/my-org/source-provider-instances/instance-1/delete-inactive-authorizations', 'POST')
                ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
                ->withAttribute('id', '11111111-1111-4111-8111-111111111111'),
        );

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body['deleted'] ?? false);
        self::assertSame(3, $body['deleted_inactive_authorizations_count'] ?? null);
        self::assertSame(2, $body['deleted_source_oauth_accounts_count'] ?? null);
    }

    #[Test]
    public function storeCreatesInstanceInActorContext(): void
    {
        $actorContext = new ActorContext('org-1', 'user-1', 'owner');

        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('createSourceProviderInstanceInContext')
            ->with(
                $actorContext,
                self::callback(
                    static fn (SourceProviderInstanceRequestDto $sourceProviderInstanceRequestDto): bool => SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED === $sourceProviderInstanceRequestDto->providerType
                        && 'Company GitLab' === $sourceProviderInstanceRequestDto->label
                        && 'https://gitlab.company.test' === $sourceProviderInstanceRequestDto->baseUrl
                        && 'client-id' === $sourceProviderInstanceRequestDto->clientId
                        && 'client-secret' === $sourceProviderInstanceRequestDto->clientSecret
                        && SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN === $sourceProviderInstanceRequestDto->authMode,
                ),
            )
            ->willReturn(new SourceProviderInstanceDto(
                id: '11111111-1111-4111-8111-111111111111',
                organizationId: 'org-1',
                providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                clientId: 'client-id',
                hasClientSecret: true,
                authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
                status: SourceProviderInstance::STATUS_ACTIVE,
            ))
        ;

        $serverRequest = new ServerRequest([], [], '/organization/my-org/source-provider-instances', 'POST')
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(SourceProviderInstanceRequest::class, new SourceProviderInstanceRequest(
                providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                clientId: 'client-id',
                clientSecret: 'client-secret',
                authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
            ))
        ;

        $response = new SourceProviderInstanceStoreHandler($sourceApiFacade, new SourceProviderInstancePresenter())->handle($serverRequest);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('11111111-1111-4111-8111-111111111111', $body['instance']['id'] ?? null);
        self::assertArrayNotHasKey('client_secret', $body['instance']);
    }

    /** @param non-empty-string $method */
    #[Test]
    #[DataProvider('instanceMutations')]
    public function mutationsReturnTheCompleteInstanceContract(string $operation, string $method, string $path, int $status): void
    {
        $actorContext              = new ActorContext('org-1', 'user-1', 'owner');
        $sourceProviderInstanceDto = new SourceProviderInstanceDto(
            '11111111-1111-4111-8111-111111111111',
            'org-1',
            'gitlab_self_hosted',
            'Company GitLab',
            'https://gitlab.company.test',
            'client-id',
            true,
            'oauth2_and_token',
            'active',
            [
                'version' => '17',
            ],
        );
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method($method)->willReturn($sourceProviderInstanceDto);
        $sourceProviderInstancePresenter = new SourceProviderInstancePresenter();
        $handler                         = match ($operation) {
            'store'  => new SourceProviderInstanceStoreHandler($facade, $sourceProviderInstancePresenter),
            'update' => new SourceProviderInstanceUpdateHandler($facade, $sourceProviderInstancePresenter),
            default  => new SourceProviderInstanceStatusHandler($facade, $sourceProviderInstancePresenter),
        };
        $serverRequest = new ServerRequest(uri: $path)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute('id', '11111111-1111-4111-8111-111111111111')
            ->withAttribute(SourceProviderInstanceRequest::class, new SourceProviderInstanceRequest(
                providerType: 'gitlab_self_hosted',
                label: 'Company GitLab',
                baseUrl: 'https://gitlab.company.test',
                clientId: 'client-id',
            ))
        ;
        $response = $handler->handle($serverRequest);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame([
            'instance' => [
                'id'                => '11111111-1111-4111-8111-111111111111',
                'organization_id'   => 'org-1',
                'provider_type'     => 'gitlab_self_hosted',
                'label'             => 'Company GitLab',
                'base_url'          => 'https://gitlab.company.test',
                'client_id'         => 'client-id',
                'has_client_secret' => true,
                'auth_mode'         => 'oauth2_and_token',
                'status'            => 'active',
                'metadata'          => [
                    'version' => '17',
                ],
            ],
        ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{string, non-empty-string, string, int}> */
    public static function instanceMutations(): iterable
    {
        yield 'store' => ['store', 'createSourceProviderInstanceInContext', '/instances', 201];

        yield 'update' => ['update', 'updateSourceProviderInstanceInContext', '/instances/instance-1', 200];

        yield 'enable' => ['enable', 'activateSourceProviderInstanceInContext', '/instances/instance-1/enable', 200];

        yield 'disable' => ['disable', 'deactivateSourceProviderInstanceInContext', '/instances/instance-1/disable', 200];
    }

    #[DataProvider('optionalLoggers')]
    public function testDependencyFailureReturnsSafeResponseWithOptionalLogger(bool $withLogger): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('sourceProviderInstanceDependenciesInContext')
            ->willThrowException(new RuntimeException('token=private-secret'))
        ;
        $logger = null;
        if ($withLogger) {
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects(self::once())->method('error')->with(
                'Source provider instance dependencies failed.',
                self::callback(static function(array $context): bool {
                    self::assertSame('request-1', $context['correlation_id']);
                    self::assertArrayHasKey('exception', $context);
                    self::assertStringNotContainsString('private-secret', json_encode($context, JSON_THROW_ON_ERROR));

                    return true;
                }),
            );
        }

        $sourceProviderInstanceDependenciesHandler = new SourceProviderInstanceDependenciesHandler(
            $sourceApiFacade,
            new SourceProviderInstancePresenter(),
            $logger,
        );
        $response = $sourceProviderInstanceDependenciesHandler->handle(
            new ServerRequest()
                ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
                ->withAttribute('id', '11111111-1111-4111-8111-111111111111')
                ->withAttribute('request_id', 'request-1'),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('private-secret', (string) $response->getBody());
    }

    /** @return iterable<string, array{bool}> */
    public static function optionalLoggers(): iterable
    {
        yield 'with logger' => [true];

        yield 'without logger' => [false];
    }
}
