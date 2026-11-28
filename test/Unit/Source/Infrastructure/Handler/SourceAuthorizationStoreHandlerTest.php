<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Common\Provider\SourceProviderMetadataCatalog;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationStoreHandler;
use Packio\Source\Infrastructure\Request\ProviderAuthorizationRequest;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

#[CoversClass(SourceAuthorizationStoreHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceAuthorizationStoreHandlerTest extends TestCase
{
    private const string SOURCE_PATH = '/organization/my-org/sources/33333333-3333-4333-8333-333333333333';

    #[Test]
    #[DataProvider('validReturnTargets')]
    public function redirectsToValidReturnTo(string $returnTo): void
    {
        self::assertSame($returnTo, $this->redirectFor($returnTo));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validReturnTargets(): iterable
    {
        yield 'plain' => [self::SOURCE_PATH];

        yield 'with query' => [self::SOURCE_PATH . '?tab=activity&page=2'];
    }

    #[Test]
    #[DataProvider('ignoredReturnTargets')]
    public function ignoresInvalidReturnTo(string $returnTo): void
    {
        self::assertSame('/organization/my-org/sources', $this->redirectFor($returnTo));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredReturnTargets(): iterable
    {
        yield 'empty' => [''];

        yield 'foreign organization' => ['/organization/other/sources/33333333-3333-4333-8333-333333333333'];

        yield 'external url' => ['https://evil.test/organization/my-org/sources/33333333-3333-4333-8333-333333333333'];

        yield 'protocol relative' => ['//evil.test/organization/my-org/sources/33333333-3333-4333-8333-333333333333'];

        yield 'not a uuid' => ['/organization/my-org/sources/abc'];

        yield 'list page' => ['/organization/my-org/sources'];

        yield 'extra path' => [self::SOURCE_PATH . '/delete'];

        yield 'fragment' => [self::SOURCE_PATH . '#x'];

        yield 'crlf' => [self::SOURCE_PATH . "?a=1\r\nX-Evil: 1"];

        yield 'trailing newline' => [self::SOURCE_PATH . "\n"];
    }

    private function redirectFor(string $returnTo): string
    {
        $request = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
            ->withAttribute(
                ProviderAuthorizationRequest::class,
                new ProviderAuthorizationRequest(provider: 'github', authorizationToken: 't', returnTo: $returnTo),
            )
        ;

        return new SourceAuthorizationStoreHandler(
            $this->createStub(SourceApiFacadeInterface::class),
            new SourceAuthorizationStrategyResolver(),
            new InertiaNotificationMessenger(),
            new SourceProviderMetadataCatalog(),
        )->handle($request)->getHeaderLine('Location');
    }
}
