<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\Psr7\Utils;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function ctype_digit;
use function fopen;
use function min;
use function rawurldecode;
use function str_starts_with;
use function strlen;

final readonly class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private ResourceLimits $resourceLimits) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $limit = str_starts_with(rawurldecode($request->getUri()->getPath()), '/webhooks/')
            ? $this->resourceLimits->webhookBytes : $this->resourceLimits->requestBytes;
        $declared = $request->getHeaderLine('Content-Length');
        if ('' !== $declared && (! ctype_digit($declared) || (float) $declared > $limit)) {
            return $this->tooLarge();
        }
        $stream = $request->getBody();
        if (null !== $stream->getSize() && $stream->getSize() > $limit) {
            return $this->tooLarge();
        }
        // Verify actual bytes even without Content-Length, before body parsing.
        $copy  = Utils::streamFor(fopen('php://temp/maxmemory:1048576', 'w+b'));
        $bytes = 0;

        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (true) {
                $chunk = $stream->read(min(8192, $limit - $bytes + 1));
                if ('' === $chunk) {
                    if ($stream->eof()) {
                        break;
                    }

                    throw new RuntimeException('Request body could not be read.');
                }
                $bytes += strlen($chunk);
                if ($bytes > $limit) {
                    $copy->close();

                    return $this->tooLarge();
                }
                $copy->write($chunk);
            }
            $copy->rewind();

            return $handler->handle($request->withBody($copy));
        } finally {
            $copy->close();
        }
    }

    private function tooLarge(): ResponseInterface
    {
        return new JsonResponse([
            'message' => 'Request body is too large.',
        ], 413);
    }
}
