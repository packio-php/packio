<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use Common\Runtime\RuntimeResetterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\ObjectMapper\Contract\WarmableObjectMapperInterface;
use Throwable;

use function gc_collect_cycles;
use function getmypid;
use function is_string;

final readonly class LongRunningHttpApplication
{
    public function __construct(
        private RequestHandlerInterface $requestHandler,
        private RuntimeResetterInterface $runtimeResetter,
        private ?LoggerInterface $logger = null,
        private ?WarmableObjectMapperInterface $warmableObjectMapper = null,
    ) {}

    public function bootstrap(string $runtime): void
    {
        $this->warmableObjectMapper?->warmup();

        $this->logger?->info('Long-running HTTP runtime bootstrapped', [
            'runtime' => $runtime,
            'pid'     => getmypid(),
        ]);
    }

    /**
     * @param callable(ResponseInterface): void $sendResponse
     * @param null|callable(Throwable): void    $sendFailureResponse
     */
    public function handleRequest(
        ServerRequestInterface $serverRequest,
        callable $sendResponse,
        ?callable $sendFailureResponse = null,
    ): void {
        try {
            try {
                $response = $this->requestHandler->handle($serverRequest);
            } catch (Throwable $exception) {
                $this->logRequestFailure($exception, $serverRequest);

                if (null !== $sendFailureResponse) {
                    $sendFailureResponse($exception);
                }

                throw new ApplicationRequestFailedException($exception);
            }

            $sendResponse($response);
        } finally {
            try {
                $this->runtimeResetter->reset();
            } finally {
                gc_collect_cycles();
            }
        }
    }

    private function logRequestFailure(Throwable $throwable, ServerRequestInterface $serverRequest): void
    {
        $requestId = $serverRequest->getAttribute('request_id');

        $this->logger?->error('Long-running HTTP request failed', [
            'exception_class' => $throwable::class,
            'method'          => $serverRequest->getMethod(),
            'path'            => $serverRequest->getUri()->getPath(),
            'request_id'      => is_string($requestId) && '' !== $requestId ? $requestId : null,
        ]);
    }
}
