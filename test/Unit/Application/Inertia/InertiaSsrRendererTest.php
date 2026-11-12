<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Inertia;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Packio\Application\Inertia\InertiaSsrRenderer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\InertiaPsr15\Model\Page;

use function json_decode;

#[CoversClass(InertiaSsrRenderer::class)]
#[AllowMockObjectsWithoutExpectations]
final class InertiaSsrRendererTest extends TestCase
{
    #[Test]
    public function itSkipsRenderingWhenDisabled(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::never())->method('request');

        $inertiaSsrRenderer = new InertiaSsrRenderer($httpClient, false, 'http://ssr.invalid/render', 1.0, 0.2);

        self::assertNull($inertiaSsrRenderer->render(Page::from('Landing')));
    }

    #[Test]
    public function itReturnsRenderedHeadAndBodyFromSsrRuntime(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'http://ssr.invalid/render',
                self::callback(static function(array $options): bool {
                    $payload = json_decode((string) $options['body'], true);

                    return 'Packages/Index' === ($payload['component'] ?? null)
                        && '/organization/acme/packages' === ($payload['url'] ?? null)
                        && 'application/json' === ($options['headers']['Content-Type'] ?? null)
                        && false === ($options['http_errors'] ?? true);
                }),
            )
            ->willReturn(new Response(200, [], '{"head":["<title>Packages</title>"],"body":"<main>Rendered</main>"}'))
        ;

        $inertiaSsrRenderer = new InertiaSsrRenderer($httpClient, true, 'http://ssr.invalid/render', 1.0, 0.2);

        $result = $inertiaSsrRenderer->render(Page::from('Packages/Index', [
            'packages' => [],
        ], '/organization/acme/packages'));

        self::assertNotNull($result);
        self::assertSame(['<title>Packages</title>'], $result->head);
        self::assertSame('<main>Rendered</main>', $result->body);
    }

    #[Test]
    public function itFallsBackWhenSsrRuntimeFails(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willThrowException(new RuntimeException('connection refused'));

        $inertiaSsrRenderer = new InertiaSsrRenderer($httpClient, true, 'http://ssr.invalid/render', 1.0, 0.2);

        self::assertNull($inertiaSsrRenderer->render(Page::from('Profile/Show')));
    }

    #[Test]
    public function itFallsBackOnNonSuccessfulSsrResponse(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(new Response(500, [], '{"error":"SSR render failed"}'));

        $inertiaSsrRenderer = new InertiaSsrRenderer($httpClient, true, 'http://ssr.invalid/render', 1.0, 0.2);

        self::assertNull($inertiaSsrRenderer->render(Page::from('Admin/Dashboard')));
    }
}
