<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Handler;

use Common\Markdown\MarkdownToHtmlRenderer;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Handler\LegalPageHandler;
use Packio\Application\Legal\LegalDocuments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(LegalPageHandler::class)]
final class LegalPageHandlerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'legal');
        file_put_contents($this->file, "# Terms\n\nBe nice.");
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    #[Test]
    public function rendersEnabledDocument(): void
    {
        $inertia = $this->createMock(InertiaInterface::class);
        $inertia->expects(self::once())->method('render')->with('Legal/Show', [
            'title' => 'Terms of Service',
            'html'  => "<h1>Terms</h1>\n<p>Be nice.</p>",
        ])->willReturn(new EmptyResponse());

        $this->handler($this->createStub(RequestHandlerInterface::class))->handle(
            new ServerRequest(uri: '/terms')->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $inertia),
        );
    }

    #[Test]
    public function disabledDocumentGetsTheNormalNotFoundResponse(): void
    {
        $notFound        = new EmptyResponse(404);
        $notFoundHandler = $this->createMock(RequestHandlerInterface::class);
        $notFoundHandler->expects(self::once())->method('handle')->willReturn($notFound);

        self::assertSame($notFound, $this->handler($notFoundHandler)->handle(new ServerRequest(uri: '/privacy')));
    }

    private function handler(RequestHandlerInterface $notFoundHandler): LegalPageHandler
    {
        return new LegalPageHandler(new LegalDocuments('', $this->file, new MarkdownToHtmlRenderer()), $notFoundHandler);
    }
}
