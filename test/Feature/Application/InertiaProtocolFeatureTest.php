<?php

declare(strict_types=1);

namespace PackioTest\Feature\Application;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use LogicException;
use Packio\Application\Release\AppReleaseVersion;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Model\OnceProp;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Service\Inertia;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;

use function array_keys;
use function getenv;
use function htmlspecialchars;
use function json_decode;
use function putenv;

final class InertiaProtocolFeatureTest extends FeatureTestCase
{
    private false|string $previousSsrEnabled;

    protected function setUp(): void
    {
        $this->previousSsrEnabled = getenv('INERTIA_SSR_ENABLED');
        putenv('INERTIA_SSR_ENABLED=false');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (false === $this->previousSsrEnabled) {
            putenv('INERTIA_SSR_ENABLED');
        } else {
            putenv('INERTIA_SSR_ENABLED=' . $this->previousSsrEnabled);
        }

        parent::tearDown();
    }

    #[Test]
    public function itRendersTheInitialPageUsingTheV3HtmlContract(): void
    {
        $this->get('/auth/login')->assertStatus(200)->assertInitialInertiaPage();
    }

    #[Test]
    public function itReturnsTheV3InertiaNavigationContract(): void
    {
        $version = $this->currentVersion();

        $this->get('/auth/login', $this->inertiaHeaders())
            ->assertStatus(200)
            ->assertInertiaResponse()
            ->assertInertiaComponent('Auth/Login')
            ->assertInertiaVersion($version)
            ->assertInertiaProp('errors', [])
        ;
    }

    #[Test]
    public function itSharesOnlyReleaseVersionInAppProp(): void
    {
        $props = $this->get('/auth/login', $this->inertiaHeaders())->assertInertiaResponse()->inertiaPage()['props'];

        self::assertSame(['version'], array_keys($props['app']));
        self::assertArrayNotHasKey('og_title', $props);
        self::assertArrayNotHasKey('og_description', $props);
    }

    #[Test]
    public function itKeepsDocumentTitleAndMetaFromBranding(): void
    {
        $branding = $this->get('/auth/login', $this->inertiaHeaders())->inertiaPage()['props']['branding'];
        $body     = $this->get('/auth/login')->assertStatus(200)->body();

        self::assertStringContainsString('<title>' . htmlspecialchars($branding['name']) . '</title>', $body);
        self::assertStringContainsString('name="apple-mobile-web-app-title" content="' . htmlspecialchars($branding['name']) . '"', $body);
        self::assertStringContainsString('property="og:description" content="' . htmlspecialchars($branding['tagline']) . '"', $body);
    }

    #[Test]
    public function itAppliesOnlyAndExceptPartialReloadFiltersInOrder(): void
    {
        $testResponse = $this->get('/auth/login', $this->partialHeaders('app'));
        $testResponse->assertInertiaResponse()->assertInertiaProp('app.version.sha', $this->currentVersion());
        self::assertArrayNotHasKey('auth', $testResponse->inertiaPage()['props']);

        $nestedOnly = $this->get('/auth/login', $this->partialHeaders('app.version.sha'));
        $nestedOnly->assertInertiaResponse()->assertInertiaProp('app.version.sha', $this->currentVersion());
        self::assertArrayHasKey('version', $nestedOnly->inertiaPage()['props']['app']);

        $except = $this->get('/auth/login', $this->partialHeaders(null, 'app.version.sha'));
        $except->assertInertiaResponse();
        self::assertArrayNotHasKey('sha', $except->inertiaPage()['props']['app']['version']);

        $onlyAndExcept = $this->get('/auth/login', $this->partialHeaders('app', 'app.version.sha'));
        $onlyAndExcept->assertInertiaResponse();
        self::assertArrayNotHasKey('sha', $onlyAndExcept->inertiaPage()['props']['app']['version']);
    }

    #[Test]
    public function itReplacesSharedAndNestedPropsAfterOnlyAndExceptFiltering(): void
    {
        $serverRequest = $this->inertiaRequest('GET', 'provider-version')
            ->withHeader('X-Inertia-Partial-Component', 'Example/Page')
            ->withHeader('X-Inertia-Partial-Data', 'shared,page')
            ->withHeader('X-Inertia-Partial-Except', 'shared.remove,page.nested.remove')
        ;

        $response = $this->concreteInertiaMiddleware('provider-version')->process(
            $serverRequest,
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                    if (! $inertia instanceof InertiaInterface) {
                        throw new LogicException('Inertia middleware did not attach an Inertia service.');
                    }

                    $inertia->share('shared', [
                        'keep'   => 'shared value',
                        'remove' => 'must not be merged back',
                    ]);

                    return $inertia->render('Example/Page', [
                        'page'    => [
                            'keep'   => 'page value',
                            'nested' => [
                                'keep'   => 'nested value',
                                'remove' => 'must not be merged back',
                            ],
                        ],
                        'omitted' => 'not selected by only',
                    ]);
                }
            },
        );

        /** @var array{props: array<string, mixed>} $page */
        $page = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('shared value', $page['props']['shared']['keep']);
        self::assertArrayNotHasKey('remove', $page['props']['shared']);
        self::assertSame('page value', $page['props']['page']['keep']);
        self::assertSame('nested value', $page['props']['page']['nested']['keep']);
        self::assertArrayNotHasKey('remove', $page['props']['page']['nested']);
        self::assertArrayNotHasKey('omitted', $page['props']);
    }

    #[Test]
    public function itUsesTheVersionProviderAsTheOnlyVersionSource(): void
    {
        $response = $this->concreteInertiaMiddleware('provider-version')->process(
            $this->inertiaRequest('GET', 'provider-version'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                    if (! $inertia instanceof InertiaInterface) {
                        throw new LogicException('Inertia middleware did not attach an Inertia service.');
                    }

                    return $inertia->render('Example/Page');
                }
            },
        );

        /** @var array{version: string} $page */
        $page = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('provider-version', $page['version']);
    }

    #[Test]
    public function itDisablesPartialFilteringWhenTheComponentDoesNotMatch(): void
    {
        $testResponse = $this->get('/auth/login', [
            ...$this->inertiaHeaders(),
            'X-Inertia-Partial-Component' => 'Other/Component',
            'X-Inertia-Partial-Data'      => 'app',
        ]);

        $testResponse->assertInertiaResponse();
        self::assertArrayHasKey('auth', $testResponse->inertiaPage()['props']);
    }

    #[Test]
    public function itRejectsStaleAssetVersionsBeforeThePageHandlerRuns(): void
    {
        $this->withCsrf()->post('/auth/reset-password', [
            'selector' => 'stale-version-selector',
        ], [
            'X-Inertia' => 'true',
            'Referer'   => '/auth/reset-password',
        ])->assertRedirect('/auth/reset-password');

        $version = $this->currentVersion();

        $testResponse = $this->get('/auth/login', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => $version . '-stale',
        ]);
        $testResponse
            ->assertStatus(409)
            ->assertInertiaLocation('/auth/login')
            ->assertHeader('X-Inertia-Version', $version)
            ->assertNoInertiaResponse()
        ;
        self::assertSame('', $testResponse->body(), 'Early version handling must skip page rendering and shared flash consumption.');

        $freshResponse = $this->get('/auth/login', $this->inertiaHeaders());
        $freshResponse
            ->assertInertiaResponse()
            ->assertInertiaComponent('Auth/Login')
        ;
        $page  = $freshResponse->inertiaPage();
        $props = $page['props'];
        self::assertSame('Invalid request data.', $page['flash']['error'] ?? null);
        self::assertArrayNotHasKey('selector', $props['old']);
        self::assertNotSame([], $props['errors'] ?? []);
    }

    #[Test]
    public function itOmitsFalseHistoryMetadataAndIncludesEnabledHistoryMetadata(): void
    {
        $page = Page::from('Auth/Login', [], '/auth/login', $this->currentVersion());
        self::assertArrayNotHasKey('clearHistory', $page->jsonSerialize());
        self::assertArrayNotHasKey('encryptHistory', $page->jsonSerialize());

        $historyPage = $page->clearHistory()->encryptHistory();
        self::assertTrue($historyPage->jsonSerialize()['clearHistory']);
        self::assertTrue($historyPage->jsonSerialize()['encryptHistory']);
    }

    #[Test]
    public function itAppliesV3RedirectRulesForMutationsExternalLocationsAndFragments(): void
    {
        $inertiaMiddleware = $this->inertiaMiddleware();

        $response = $inertiaMiddleware->process(
            $this->inertiaRequest('PATCH'),
            $this->respondingWith(new RedirectResponse('/after-mutation')),
        );
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/after-mutation', $response->getHeaderLine('Location'));

        $external = $inertiaMiddleware->process(
            $this->inertiaRequest('GET'),
            $this->respondingWith(new Response()->withStatus(409)->withHeader('X-Inertia-Location', 'https://outside.example/next')),
        );
        self::assertSame(409, $external->getStatusCode());
        self::assertSame('https://outside.example/next', $external->getHeaderLine('X-Inertia-Location'));
        self::assertFalse($external->hasHeader('X-Inertia'));

        $fragment = $inertiaMiddleware->process(
            $this->inertiaRequest('GET'),
            $this->respondingWith(new RedirectResponse('/after-mutation#details')),
        );
        self::assertSame(409, $fragment->getStatusCode());
        self::assertSame('/after-mutation#details', $fragment->getHeaderLine('X-Inertia-Redirect'));
        self::assertFalse($fragment->hasHeader('Location'));
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        return [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ];
    }

    /** @return array<string, string> */
    private function partialHeaders(?string $only = null, ?string $except = null): array
    {
        $headers = [
            ...$this->inertiaHeaders(),
            'X-Inertia-Partial-Component' => 'Auth/Login',
        ];

        if (null !== $only) {
            $headers['X-Inertia-Partial-Data'] = $only;
        }

        if (null !== $except) {
            $headers['X-Inertia-Partial-Except'] = $except;
        }

        return $headers;
    }

    private function currentVersion(): string
    {
        $release = $this->getService(AppReleaseVersion::class);
        self::assertInstanceOf(AppReleaseVersion::class, $release);

        $version = $release->toArray()['sha'];
        self::assertNotSame('', $version, 'The test release must expose an Inertia asset version');

        return $version;
    }

    private function inertiaRequest(string $method, ?string $version = null): ServerRequestInterface
    {
        $serverRequest = new ServerRequest(uri: 'https://localhost/protocol', method: $method)
            ->withHeader('X-Inertia', 'true')
        ;

        return null === $version ? $serverRequest : $serverRequest->withHeader('X-Inertia-Version', $version);
    }

    private function respondingWith(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function inertiaMiddleware(): InertiaMiddleware
    {
        return new InertiaMiddleware(new class implements InertiaFactoryInterface {
            public function fromRequest(ServerRequestInterface $request, ?string $version): InertiaInterface
            {
                return new class implements InertiaInterface {
                    public function render(string $component, array $props = []): ResponseInterface
                    {
                        return new Response();
                    }

                    public function share(string $key, mixed $value = null): void {}

                    public function shareOnce(string $key, mixed $value): OnceProp
                    {
                        throw new LogicException('Not used by redirect protocol tests.');
                    }

                    public function flash(array|string $key, mixed $value = null): static
                    {
                        return $this;
                    }

                    public function encryptHistory(bool $enabled = true): void {}

                    public function clearHistory(bool $enabled = true): void {}

                    public function preserveFragment(bool $enabled = true): void {}

                    public function location(ResponseInterface|string $destination, int $status = 302): ResponseInterface
                    {
                        return new Response()->withStatus($status);
                    }
                };
            }
        });
    }

    private function concreteInertiaMiddleware(string $version): InertiaMiddleware
    {
        return new InertiaMiddleware(
            new class implements InertiaFactoryInterface {
                public function fromRequest(ServerRequestInterface $request, ?string $version): InertiaInterface
                {
                    return new Inertia(
                        $request,
                        new ResponseFactory(),
                        new StreamFactory(),
                        new class implements RootViewProviderInterface {
                            public function __invoke(Page $page): string
                            {
                                return '';
                            }
                        },
                        $version,
                    );
                }
            },
            versionProvider: new readonly class($version) implements InertiaVersionProviderInterface {
                public function __construct(private string $version) {}

                public function currentVersion(ServerRequestInterface $request): string
                {
                    return $this->version;
                }
            },
        );
    }
}
