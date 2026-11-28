<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use Common\Provider\SourceProviderMetadataCatalog;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\Session;
use Mezzio\Session\SessionMiddleware;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
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
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Throwable;

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

    #[Test]
    #[DataProvider('failures')]
    public function failureSetsFieldErrorWithoutErrorToast(Throwable $exception, string $message): void
    {
        $facade = $this->createStub(SourceApiFacadeInterface::class);
        $facade->method('authorizeProviderInContext')->willThrowException($exception);
        $session = new Session([]);
        $inertia = new TestInertia();
        $request = $this->request(self::SOURCE_PATH)->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session)->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia);

        $response = $this->handler($facade)->handle($request);

        self::assertSame('/organization/my-org/sources', $response->getHeaderLine('Location'));
        self::assertSame([
            'authorization_token' => [$message],
        ], new ValidationRedirectState()->consumeErrors($request));
        self::assertArrayNotHasKey('toasts', $inertia->flash);
    }

    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'generic' => [new RuntimeException('secret detail'), 'Packio could not verify this token. Check that it is valid and has repository read access.'];

        yield 'public' => [new PublicValidationException('Token is expired.'), 'Token is expired.'];
    }

    private function request(string $returnTo): ServerRequest
    {
        return new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, new Session([]))
            ->withAttribute(
                ProviderAuthorizationRequest::class,
                new ProviderAuthorizationRequest(provider: 'github', authorizationToken: 't', returnTo: $returnTo),
            )
        ;
    }

    private function handler(SourceApiFacadeInterface $facade): SourceAuthorizationStoreHandler
    {
        return new SourceAuthorizationStoreHandler(
            $facade,
            new SourceAuthorizationStrategyResolver(),
            new InertiaNotificationMessenger(),
            new SourceProviderMetadataCatalog(),
            new ValidationRedirectState(),
        );
    }

    private function redirectFor(string $returnTo): string
    {
        $facade = $this->createStub(SourceApiFacadeInterface::class);
        $facade->method('authorizeProviderInContext')->willReturn(new ProviderAuthorizationDto('auth-1', 'org-1', 'github', 'token', 'active'));

        return $this->handler($facade)->handle($this->request($returnTo))->getHeaderLine('Location');
    }
}
