<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth\Client;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;

use function http_build_query;

final readonly class OAuthHttpClient
{
    public function __construct(private UpstreamHttpClient $upstreamHttpClient) {}

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    public function postForm(string $url, array $payload, array $headers): array
    {
        try {
            $response = $this->upstreamHttpClient->request(
                'POST',
                $url,
                $headers,
                body: http_build_query($payload),
            );
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

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    public function getJson(string $url, array $headers): array
    {
        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $headers);
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
