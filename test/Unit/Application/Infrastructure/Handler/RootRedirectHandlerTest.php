<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Handler\RootRedirectHandler;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

#[CoversClass(RootRedirectHandler::class)]
final class RootRedirectHandlerTest extends TestCase
{
    #[Test]
    public function landingReceivesInstallationBranding(): void
    {
        $oauth = $this->createStub(OAuthApiFacadeInterface::class);
        $oauth->method('providerAvailabilities')->willReturn([]);

        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with('Landing', [
            'product_name'    => 'Acme',
            'tagline'         => 'Acme registry',
            'domain'          => 'registry.example.com',
            'app_base_url'    => 'https://registry.example.com/',
            'oauth_providers' => [],
        ])->willReturn(new EmptyResponse());

        $brandingConfig = new BrandingConfig([
            'product_name' => 'Acme',
            'tagline'      => 'Acme registry',
            'domain'       => 'registry.example.com',
            'app_url'      => 'https://registry.example.com/',
        ]);

        new RootRedirectHandler($oauth, $brandingConfig)->handle(
            new ServerRequest()->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia),
        );
    }
}
