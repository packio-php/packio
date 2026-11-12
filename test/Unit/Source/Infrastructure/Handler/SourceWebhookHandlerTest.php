<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\WebhookRegistrationResultDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Handler\SourceWebhookRegisterHandler;
use Packio\Source\Infrastructure\Handler\SourceWebhookRotateHandler;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(SourceWebhookRegisterHandler::class)]
#[CoversClass(SourceWebhookRotateHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceWebhookHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
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

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseRbacWithoutLegacyManageGuard(string $handlerClass): void
    {
        $postAttributes = new ReflectionClass($handlerClass)->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);
        self::assertNull($postAttributes[0]->newInstance()->middleware);
    }

    /** @return iterable<string, array{class-string}> */
    public static function ownerOnlyHandlerClasses(): iterable
    {
        yield 'source webhook register' => [SourceWebhookRegisterHandler::class];

        yield 'source webhook rotate' => [SourceWebhookRotateHandler::class];
    }

    #[Test]
    public function registerPublishesReconnectToastForReauthorizationRequiredFailure(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())->method('findSource')->with('33333333-3333-4333-8333-333333333333')->willReturn(new SourceDto(
            id: '33333333-3333-4333-8333-333333333333',
            provider: 'github',
            type: 'github',
            displayName: 'acme/package',
            canonicalUrl: 'https://github.com/acme/package',
            url: 'https://github.com/acme/package',
            owner: 'acme',
            repository: 'package',
            defaultRef: 'main',
            organizationId: 'org-1',
        ));
        $sourceApiFacade->expects(self::once())->method('supportsWebhookAutoRegistration')->with('github')->willReturn(true);
        $sourceApiFacade->expects(self::once())->method('tryRegisterWebhook')->with('33333333-3333-4333-8333-333333333333')->willReturn(new WebhookRegistrationResultDto(
            success: false,
            externalId: '',
            message: 'Provider authorization is invalid or expired. Please reconnect.',
            authHealth: 'reauthorization_required',
            authFailureReason: 'OAuth account is inactive. Reauthorization required.',
        ));
        $testInertia   = new TestInertia();
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute('sourceId', '33333333-3333-4333-8333-333333333333')
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;

        $response = new SourceWebhookRegisterHandler($sourceApiFacade, new SourcePolicy(), new InertiaNotificationMessenger())->handle($serverRequest);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/my-org/sources/33333333-3333-4333-8333-333333333333', $response->getHeaderLine('Location'));
        self::assertSame([[
            'type'     => 'error',
            'message'  => 'Provider authorization is invalid or expired. Please reconnect.',
            'duration' => null,
            'position' => null,
        ]], $testInertia->flash['toasts']);
    }
}
