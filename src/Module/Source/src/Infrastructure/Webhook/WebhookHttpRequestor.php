<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Webhook;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;

final readonly class WebhookHttpRequestor
{
    public function __construct(private UpstreamHttpClient $upstreamHttpClient) {}

    /**
     * @param array<string, string>     $headers
     * @param null|array<string, mixed> $body
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        try {
            $response = $this->upstreamHttpClient->request($method, $url, $headers, $body);
        } catch (UpstreamHttpException) {
            return [
                'status' => 0,
                'body'   => null,
            ];
        }

        return [
            'status' => $response->statusCode(),
            'body'   => $response->jsonArray(),
        ];
    }
}
