<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Handler;

use Laminas\Diactoros\Response\TextResponse;
use Packio\Rest\Infrastructure\OpenApi\OpenApiAccessGuard;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function basename;
use function file_get_contents;
use function in_array;
use function is_file;
use function is_readable;
use function is_string;
use function rtrim;

#[Get('/api/doc/assets/:asset', name: 'api.doc.asset')]
final readonly class OpenApiAssetHandler implements RequestHandlerInterface
{
    private const array ALLOWED_ASSETS = [
        'swagger-ui.css',
        'swagger-ui-bundle.js',
        'swagger-ui-standalone-preset.js',
        'favicon-16x16.png',
        'favicon-32x32.png',
    ];

    /**
     * @param array{enabled?: bool, public?: bool, swagger_ui_assets?: string} $docsConfig
     */
    public function __construct(
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private OpenApiAccessGuard $openApiAccessGuard,
        private array $docsConfig = [],
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $denied = $this->openApiAccessGuard->deny($request);
        if ($denied instanceof ResponseInterface) {
            return $denied;
        }

        $asset = $request->getAttribute('asset');
        $asset = is_string($asset) ? basename($asset) : '';
        if (! in_array($asset, self::ALLOWED_ASSETS, true)) {
            return $this->apiErrorResponseFactory->notFound('Asset not found.');
        }

        $root = rtrim((string) ($this->docsConfig['swagger_ui_assets'] ?? ''), '/');
        $path = $root . '/' . $asset;
        if ('' === $root || ! is_file($path) || ! is_readable($path)) {
            return $this->apiErrorResponseFactory->notFound('Asset not found.');
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return $this->apiErrorResponseFactory->notFound('Asset not found.');
        }

        return new TextResponse($contents, 200, [
            'Content-Type'  => $this->contentType($asset),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function contentType(string $asset): string
    {
        return match ($asset) {
            'swagger-ui.css'                         => 'text/css; charset=utf-8',
            'favicon-16x16.png', 'favicon-32x32.png' => 'image/png',
            default                                  => 'application/javascript; charset=utf-8',
        };
    }
}
