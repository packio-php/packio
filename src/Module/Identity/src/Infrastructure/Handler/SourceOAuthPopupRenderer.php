<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;

use function htmlspecialchars;
use function json_encode;

final readonly class SourceOAuthPopupRenderer
{
    public function render(
        bool $success,
        string $provider,
        ?string $sourceOAuthAccountId,
        ?string $error = null
    ): ResponseInterface {
        $payload = json_encode(
            [
                'type'                 => 'packio:source-oauth',
                'success'              => $success,
                'provider'             => $provider,
                'sourceOAuthAccountId' => $sourceOAuthAccountId,
                'error'                => $error,
            ],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );

        $message = $success ? 'Authorization complete. You can close this window.' : 'Authorization failed.';
        $html    = '<!doctype html><html><head><meta charset="utf-8"><title>OAuth</title></head><body>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<script>if (window.opener) { window.opener.postMessage(' . $payload . ', window.location.origin); window.close(); }</script>'
            . '</body></html>';

        return new HtmlResponse($html);
    }
}
