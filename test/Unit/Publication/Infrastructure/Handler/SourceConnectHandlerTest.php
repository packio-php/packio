<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Publication\Application\Command\ConnectSourcesUseCase;
use Packio\Publication\Application\Dto\ConnectSourceOutcome;
use Packio\Publication\Application\Dto\ConnectSourcesResult;
use Packio\Publication\Infrastructure\Handler\SourceConnectHandler;
use Packio\Publication\Infrastructure\Request\ConnectSourcesCommandFactory;
use Packio\Publication\Infrastructure\Request\SourceConnectRequest;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

use function array_fill;

#[CoversClass(SourceConnectHandler::class)]
final class SourceConnectHandlerTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testMapsBatchResult(
        int $queued,
        int $skipped,
        int $failed,
        string $type,
        string $message
    ): void {
        $connectSourceOutcome = new ConnectSourceOutcome('repo', 'internal_error', 'safe');
        $useCase              = $this->createMock(ConnectSourcesUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(self::callback(static fn ($command): bool => ['a/b'] === $command->repositories && 'org' === $command->actorContext->organizationId))->willReturn(new ConnectSourcesResult(array_fill(0, $queued, $connectSourceOutcome), array_fill(0, $skipped, $connectSourceOutcome), array_fill(0, $failed, $connectSourceOutcome)));
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('flash')->with('toasts', [[
            'type'     => $type,
            'message'  => $message,
            'duration' => null,
            'position' => null,
        ]]);
        $serverRequest = RequestContextAttributes::withOrganizationContext(new ServerRequest(), 'example', new OrganizationDto('org', 'example', 'Example'), actorContext: new ActorContext('org', 'user', 'owner'))
            ->withAttribute(SourceConnectRequest::class, new SourceConnectRequest(provider: 'github', resourceUrl: ' a/b '))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia)
        ;
        $response = new SourceConnectHandler($useCase, new ConnectSourcesCommandFactory(), new InertiaNotificationMessenger())->handle($serverRequest);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/example/sources', $response->getHeaderLine('Location'));
    }

    /** @return iterable<string, array{int, int, int, string, string}> */
    public static function outcomes(): iterable
    {
        yield 'queued' => [2, 0, 0, 'success', '2 source(s) queued for import. You can leave this page.'];

        yield 'partial' => [1, 1, 1, 'warning', '1 source(s) queued, 1 already queued, 1 failed.'];

        yield 'failed' => [0, 0, 2, 'error', 'Unable to queue sources for import.'];

        yield 'repeat' => [0, 2, 0, 'success', '0 source(s) queued, 2 already queued, 0 failed.'];

        yield 'skipped and failed' => [0, 1, 1, 'warning', '0 source(s) queued, 1 already queued, 1 failed.'];
    }

    #[Test]
    public function routeUsesSourcesManagePermissionWithRequestContext(): void
    {
        $reflectionClass = new ReflectionClass(SourceConnectHandler::class);
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
        $reflectionClass = new ReflectionClass(SourceConnectHandler::class);
        $postAttributes  = $reflectionClass->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);

        /** @var Post $post */
        $post = $postAttributes[0]->newInstance();

        self::assertNull($post->middleware);
    }
}
