<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Policy\SourcePolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Handler\SourceResyncHandler;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Throwable;

#[CoversClass(SourceResyncHandler::class)]
final class SourceResyncHandlerTest extends TestCase
{
    private const string SOURCE_ID = '33333333-3333-4333-8333-333333333333';

    #[Test]
    public function routeUsesSourcesManagePermissionWithRequestContext(): void
    {
        $reflectionClass = new ReflectionClass(SourceResyncHandler::class);
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

    #[Test]
    public function routeUsesRbacWithoutLegacyManageGuard(): void
    {
        $reflectionClass = new ReflectionClass(SourceResyncHandler::class);
        $postAttributes  = $reflectionClass->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);

        /** @var Post $post */
        $post = $postAttributes[0]->newInstance();

        self::assertNull($post->middleware);
    }

    #[Test]
    public function skipsEnqueueWhenSyncIsAlreadyActive(): void
    {
        $sourceApiFacade = $this->facade('running');
        $sourceApiFacade->expects(self::never())->method('scheduleSourceSync');

        $testInertia = new TestInertia();
        $response    = $this->handle($sourceApiFacade, $testInertia);

        self::assertSame('/organization/my-org/sources/' . self::SOURCE_ID, $response->getHeaderLine('Location'));
        self::assertSame('info', $testInertia->flash['toasts'][0]['type']);
        self::assertSame('A sync is already queued or running.', $testInertia->flash['toasts'][0]['message']);
    }

    #[Test]
    public function enqueuesWhenNoSyncIsActive(): void
    {
        $sourceApiFacade = $this->facade(null);
        $sourceApiFacade->expects(self::once())->method('scheduleSourceSync')->with(self::SOURCE_ID, 'org-1', 5);

        $testInertia = new TestInertia();
        $this->handle($sourceApiFacade, $testInertia);

        self::assertSame('Re-sync scheduled.', $testInertia->flash['success']);
    }

    #[Test]
    public function flashesErrorWhenStatusLookupFails(): void
    {
        $sourceApiFacade = $this->facade(null, new RuntimeException('db down'));
        $sourceApiFacade->expects(self::never())->method('scheduleSourceSync');

        $testInertia = new TestInertia();
        $this->handle($sourceApiFacade, $testInertia);

        self::assertSame('Unable to schedule re-sync.', $testInertia->flash['error']);
    }

    private function facade(?string $activeStatus, ?Throwable $failure = null): MockObject&SourceApiFacadeInterface
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->willReturn(new SourceDto(
            id: self::SOURCE_ID,
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
        $activeCall = $sourceApiFacade->expects(self::once())->method('activeSourceSyncStatus')->with(self::SOURCE_ID, 'org-1');
        if ($failure instanceof Throwable) {
            $activeCall->willThrowException($failure);
        } else {
            $activeCall->willReturn($activeStatus);
        }

        return $sourceApiFacade;
    }

    private function handle(SourceApiFacadeInterface $sourceApiFacade, TestInertia $testInertia): ResponseInterface
    {
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute('sourceId', self::SOURCE_ID)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
        ;

        return new SourceResyncHandler($sourceApiFacade, new SourcePolicy(), new InertiaNotificationMessenger())->handle($serverRequest);
    }
}
