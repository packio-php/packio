<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Packio\Rest\Infrastructure\OpenApi\OpenApiAccessGuard;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/api/doc', name: 'api.doc')]
final readonly class OpenApiUiHandler implements RequestHandlerInterface
{
    public function __construct(private OpenApiAccessGuard $openApiAccessGuard) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $denied = $this->openApiAccessGuard->deny($request);
        if ($denied instanceof ResponseInterface) {
            return $denied;
        }

        return new HtmlResponse($this->html(), headers: [
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function html(): string
    {
        return <<<'HTML'
            <!doctype html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>Packio API</title>
              <link rel="stylesheet" href="/api/doc/assets/swagger-ui.css">
              <style>
                body {margin: 0; background: #fff}
                .swagger-ui .topbar .download-url-wrapper { display: none; }
                .swagger-ui .topbar .dark-mode-toggle { margin-left: auto; }
              </style>
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="/api/doc/assets/swagger-ui-bundle.js"></script>
              <script src="/api/doc/assets/swagger-ui-standalone-preset.js"></script>
              <script>
                window.ui = SwaggerUIBundle({
                  url: '/api/doc.json',
                  dom_id: '#swagger-ui',
                  deepLinking: true,
                  presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                  layout: 'StandaloneLayout'
                });
              </script>
            </body>
            </html>
            HTML;
    }
}
