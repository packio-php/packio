<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Cors\Middleware\Exception\InvalidConfigurationException;
use Mezzio\Cors\Service\ConfigurationLocatorInterface;
use Mezzio\Cors\Service\CorsInterface;
use Mezzio\Cors\Service\ResponseFactoryInterface;
use Mezzio\Router\RouteResult;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Middleware\RuntimeCorsMiddleware;
use Packio\Application\Service\CorsOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RuntimeCorsMiddleware::class)]
#[CoversClass(CorsOptions::class)]
final class RuntimeCorsMiddlewareTest extends TestCase
{
    #[Test]
    public function requestWithoutOriginSkipsSettingsLookupAndDelegatesToHandler(): void
    {
        $projectSettings = $this->createProjectSettingsFacade();
        $projectSettings->expects(self::never())->method('booleanValue');

        $cors = $this->createMock(CorsInterface::class);
        $cors->expects(self::never())->method('isCorsRequest');

        $runtimeCorsCapturingHandler  = new RuntimeCorsCapturingHandler();
        $response                     = $this->middleware($projectSettings, $cors)
            ->process(new ServerRequest(), $runtimeCorsCapturingHandler)
        ;

        self::assertTrue($runtimeCorsCapturingHandler->called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function disabledRuntimeSettingSkipsInnerCorsMiddleware(): void
    {
        $projectSettings = $this->createProjectSettingsFacade();
        $projectSettings->expects(self::once())
            ->method('booleanValue')
            ->with(CorsOptions::DEFAULT_RUNTIME_SETTING_KEY, false)
            ->willReturn(false)
        ;

        $cors = $this->createMock(CorsInterface::class);
        $cors->expects(self::never())->method('isCorsRequest');

        $runtimeCorsCapturingHandler  = new RuntimeCorsCapturingHandler();
        $response                     = $this->middleware(
            $projectSettings,
            $cors,
            new CorsOptions(enabledFallback: false),
        )->process(new ServerRequest(headers: [
            'Origin' => 'https://packio.us',
        ]), $runtimeCorsCapturingHandler);

        self::assertTrue($runtimeCorsCapturingHandler->called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function enabledRuntimeSettingDelegatesToInnerCorsMiddleware(): void
    {
        $projectSettings = $this->createProjectSettingsFacade();
        $projectSettings->expects(self::once())
            ->method('booleanValue')
            ->with(CorsOptions::DEFAULT_RUNTIME_SETTING_KEY, true)
            ->willReturn(true)
        ;

        $cors = $this->createMock(CorsInterface::class);
        $cors->expects(self::once())
            ->method('isCorsRequest')
            ->willReturn(false)
        ;

        $serverRequest                      = new ServerRequest(headers: [
            'Origin' => 'https://packio.us',
        ])->withAttribute('custom.attribute', 'preserved');
        $runtimeCorsCapturingHandler  = new RuntimeCorsCapturingHandler();
        $response                     = $this->middleware($projectSettings, $cors)
            ->process($serverRequest, $runtimeCorsCapturingHandler)
        ;

        self::assertTrue($runtimeCorsCapturingHandler->called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
        self::assertSame($serverRequest, $runtimeCorsCapturingHandler->request);
        self::assertSame('preserved', $runtimeCorsCapturingHandler->request->getAttribute('custom.attribute'));
    }

    #[Test]
    public function enabledCorsRejectsARequestAlreadyRoutedBeforeNativeCors(): void
    {
        $projectSettings = $this->createProjectSettingsFacade();
        $projectSettings->expects(self::once())
            ->method('booleanValue')
            ->with(CorsOptions::DEFAULT_RUNTIME_SETTING_KEY, true)
            ->willReturn(true)
        ;

        $cors = $this->createMock(CorsInterface::class);
        $cors->expects(self::never())->method('isCorsRequest');

        $serverRequest     = new ServerRequest(headers: [
            'Origin' => 'https://packio.us',
        ])->withAttribute(RouteResult::class, RouteResult::fromRouteFailure(null))
            ->withAttribute('custom.attribute', 'preserved')
        ;
        $runtimeCorsCapturingHandler = new RuntimeCorsCapturingHandler();

        $this->expectException(InvalidConfigurationException::class);

        $this->middleware($projectSettings, $cors)->process($serverRequest, $runtimeCorsCapturingHandler);
    }

    #[Test]
    public function configuredRuntimeKeyAndFallbackArePassedToProjectSettings(): void
    {
        $projectSettings = $this->createProjectSettingsFacade();
        $projectSettings->expects(self::once())
            ->method('booleanValue')
            ->with('custom.cors.enabled', false)
            ->willReturn(false)
        ;

        $cors = $this->createMock(CorsInterface::class);
        $cors->expects(self::never())->method('isCorsRequest');

        $runtimeCorsCapturingHandler = new RuntimeCorsCapturingHandler();
        $this->middleware(
            $projectSettings,
            $cors,
            new CorsOptions(enabledFallback: false, runtimeSettingKey: 'custom.cors.enabled'),
        )->process(new ServerRequest(headers: [
            'Origin' => 'https://packio.us',
        ]), $runtimeCorsCapturingHandler);

        self::assertTrue($runtimeCorsCapturingHandler->called);
    }

    /**
     * @return MockObject&ProjectSettingsApiFacadeInterface
     */
    private function createProjectSettingsFacade(): ProjectSettingsApiFacadeInterface
    {
        return $this->createMock(ProjectSettingsApiFacadeInterface::class);
    }

    private function middleware(
        ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
        CorsInterface $cors,
        ?CorsOptions $corsOptions = null,
    ): RuntimeCorsMiddleware {
        $configurationLocator = $this->createStub(ConfigurationLocatorInterface::class);
        $responseFactory      = $this->createStub(ResponseFactoryInterface::class);

        return new RuntimeCorsMiddleware(
            new CorsMiddleware($cors, $configurationLocator, $responseFactory),
            $projectSettingsApiFacade,
            $corsOptions ?? new CorsOptions(enabledFallback: true),
        );
    }
}

final class RuntimeCorsCapturingHandler implements RequestHandlerInterface
{
    public bool $called = false;

    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called  = true;
        $this->request = $request;

        return new JsonResponse([
            'ok' => true,
        ]);
    }
}
