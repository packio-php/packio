<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

use GuzzleHttp\ClientInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Sirix\InertiaPsr15\Model\Page;
use Throwable;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

final readonly class InertiaSsrRenderer
{
    public function __construct(
        private ClientInterface $httpClient,
        private bool $enabled,
        private string $url,
        private float $timeout,
        private float $connectTimeout,
        private ?LoggerInterface $logger = null,
    ) {}

    public function render(Page $page): ?InertiaSsrRenderResult
    {
        if (! $this->enabled || '' === $this->url) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $this->url, [
                'body'            => json_encode($page, JSON_THROW_ON_ERROR),
                'connect_timeout' => $this->connectTimeout,
                'headers'         => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'http_errors'     => false,
                'timeout'         => $this->timeout,
            ]);

            if (200 !== $response->getStatusCode()) {
                $this->logger?->warning('Inertia SSR render failed with non-200 response.', [
                    'component' => $page->getComponent(),
                    'status'    => $response->getStatusCode(),
                ]);

                return null;
            }

            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                return null;
            }

            $body = $payload['body'] ?? null;
            if (! is_string($body) || '' === $body) {
                return null;
            }

            $head = $payload['head'] ?? [];
            $head = is_array($head)
                ? array_values(array_filter($head, is_string(...)))
                : [];

            return new InertiaSsrRenderResult($head, $body);
        } catch (JsonException $exception) {
            $this->logger?->warning('Inertia SSR payload could not be encoded or decoded.', [
                'component' => $page->getComponent(),
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger?->warning('Inertia SSR render request failed.', [
                'component' => $page->getComponent(),
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
        }

        return null;
    }
}
