<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Validation;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Security\Http\TrustedProxyMatcher;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Packio\Application\Validation\MappedRequestValidationPlanResolver;
use Packio\Application\Validation\ValidationFailureResponseDispatcher;
use Packio\Application\Validation\ValidationTargetPlanItem;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\ValidationFailureCollaborators;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

use function json_decode;

#[CoversClass(ValidationFailureResponseDispatcher::class)]
final class ValidationFailureResponseDispatcherTest extends TestCase
{
    use ValidationFailureCollaborators;

    #[Test]
    public function itReturnsTheExistingJsonValidationResponse(): void
    {
        $response = self::validationFailureResponseDispatcher()->dispatch(
            new ServerRequest([], [], '/example', 'POST')->withHeader('Accept', 'application/json'),
            new ValidationTargetPlanItem('ExampleRequest', [
                'message'     => 'Invalid request data.',
                'json_status' => 422,
            ]),
            [
                'confirmation_name' => ['Required.'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error'    => 'Invalid request data.',
            'messages' => [
                'confirmation_name' => ['Required.'],
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itRedirectsWithAllowlistedOldInputErrorsAndToastNotification(): void
    {
        $validationRedirectState       = new ValidationRedirectState();
        $testInertia                   = new TestInertia();
        $serverRequest                 = new ServerRequest([], [], '/organization/acme/settings', 'POST')
            ->withParsedBody([
                'confirmationName' => 'Acme',
                'password'         => 'secret',
            ])
            ->withAttribute(SessionInterface::class, new InMemorySession())
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute('organization', 'acme')
        ;
        $validationFailureResponseDispatcher = self::validationFailureResponseDispatcher($validationRedirectState);

        $response = $validationFailureResponseDispatcher->dispatch(
            $serverRequest,
            new ValidationTargetPlanItem('ExampleRequest', [
                'redirect_to' => '/organization/{organization}/settings',
                'message'     => 'Invalid request data.',
                'notify'      => 'toast',
                'level'       => 'error',
                'old_input'   => ['confirmationName'],
            ]),
            [
                'confirmation_name' => ['Required.'],
            ],
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/acme/settings', $response->getHeaderLine('Location'));
        self::assertSame([
            'confirmation_name' => ['Required.'],
        ], $validationRedirectState->consumeErrors($serverRequest));
        self::assertSame([
            'confirmationName' => 'Acme',
        ], $validationRedirectState->consumeOld($serverRequest));
        self::assertSame([[
            'type'     => 'error',
            'message'  => 'Invalid request data.',
            'duration' => null,
            'position' => null,
        ]], $testInertia->flash['toasts']);
    }

    #[Test]
    public function itDoesNotPersistOldInputForTheCanonicalDefaultPolicy(): void
    {
        $validationRedirectState = new ValidationRedirectState();
        $serverRequest           = new ServerRequest([], [], '/organization/acme/settings', 'POST')
            ->withParsedBody([
                'confirmationName' => 'Acme',
                'password'         => 'secret',
            ])
            ->withAttribute(SessionInterface::class, new InMemorySession())
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;
        $validationFailureResponseDispatcher = self::validationFailureResponseDispatcher($validationRedirectState);

        $response = $validationFailureResponseDispatcher->dispatch(
            $serverRequest,
            new ValidationTargetPlanItem('ExampleRequest', [
                MappedRequestValidationPlanResolver::DEFAULT_FAILURE_OPTIONS => true,
            ]),
            [
                'confirmation_name' => ['Required.'],
            ],
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame([], $validationRedirectState->consumeOld($serverRequest));
        self::assertSame([
            'confirmation_name' => ['Required.'],
        ], $validationRedirectState->consumeErrors($serverRequest));
    }

    #[Test]
    public function itRedirectsHomeWhenTheRefererHasAForeignOrigin(): void
    {
        self::assertSame('/', $this->refererRedirect(
            new ServerRequest([], [], 'https://packio.test/organization/acme/settings', 'POST')
                ->withHeader('Referer', 'https://evil.test/x'),
        ));
    }

    #[Test]
    public function itRedirectsToASameOriginReferer(): void
    {
        self::assertSame('https://packio.test/organization/acme/settings?tab=a', $this->refererRedirect(
            new ServerRequest([], [], 'https://packio.test/organization/acme/settings', 'POST')
                ->withHeader('Referer', 'https://packio.test/organization/acme/settings?tab=a'),
        ));
    }

    #[Test]
    public function itRedirectsToASameOriginRefererBehindATrustedProxy(): void
    {
        self::assertSame('https://packio.test/organization/acme/settings', $this->refererRedirect(
            new ServerRequest([
                'REMOTE_ADDR' => '10.0.0.1',
            ], [], 'http://internal.test:8080/organization/acme/settings', 'POST')
                ->withHeader('X-Forwarded-Proto', 'https')
                ->withHeader('X-Forwarded-Host', 'packio.test')
                ->withHeader('Referer', 'https://packio.test/organization/acme/settings'),
            new TrustedProxyRequestOriginResolver(new TrustedProxyMatcher([
                'trusted_proxies' => ['10.0.0.1'],
            ])),
        ));
    }

    #[Test]
    public function itRedirectsToARelativeRefererPathOnly(): void
    {
        foreach ([
            '/auth/reset-password?x=1' => '/auth/reset-password?x=1',
            '//evil.test/x'            => '/',
            '/\evil.test/x'            => '/',
            "/\t/evil.test"            => '/',
        ] as $referer => $expected) {
            self::assertSame($expected, $this->refererRedirect(
                new ServerRequest([], [], 'https://packio.test/auth/reset-password', 'POST')
                    ->withHeader('Referer', $referer),
            ), $referer);
        }
    }

    private function refererRedirect(
        ServerRequest $serverRequest,
        TrustedProxyRequestOriginResolver $trustedProxyRequestOriginResolver = new TrustedProxyRequestOriginResolver(),
    ): string {
        $response = self::validationFailureResponseDispatcher(trustedProxyRequestOriginResolver: $trustedProxyRequestOriginResolver)->dispatch(
            $serverRequest
                ->withAttribute(SessionInterface::class, new InMemorySession())
                ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia()),
            new ValidationTargetPlanItem('ExampleRequest', [
                MappedRequestValidationPlanResolver::DEFAULT_FAILURE_OPTIONS => true,
            ]),
            [
                'confirmation_name' => ['Required.'],
            ],
        );

        self::assertSame(302, $response->getStatusCode());

        return $response->getHeaderLine('Location');
    }
}
