<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function json_decode;

final class UpstreamHttpClientTest extends TestCase
{
    #[Test]
    public function returnsSuccessfulJsonResponse(): void
    {
        $upstreamHttpClient = $this->clientWithResponses([
            new Response(200, [
                'X-Upstream' => 'provider',
            ], '{"name":"packio"}'),
        ]);

        $upstreamHttpResponse = $upstreamHttpClient->request('GET', 'https://example.test/api');

        self::assertSame(200, $upstreamHttpResponse->statusCode());
        self::assertTrue($upstreamHttpResponse->isSuccessful());
        self::assertSame([
            'name' => 'packio',
        ], $upstreamHttpResponse->jsonArray());
        self::assertSame(['provider'], $upstreamHttpResponse->headers()['X-Upstream'] ?? null);
    }

    #[Test]
    public function returnsSuccessfulTextResponse(): void
    {
        $upstreamHttpClient = $this->clientWithResponses([
            new Response(200, [], 'plain text'),
        ]);

        $upstreamHttpResponse = $upstreamHttpClient->request('GET', 'https://example.test/readme');

        self::assertSame('plain text', $upstreamHttpResponse->body());
        self::assertNull($upstreamHttpResponse->jsonArray());
    }

    #[Test]
    public function returnsEmptyResponse(): void
    {
        $upstreamHttpClient = $this->clientWithResponses([
            new Response(204),
        ]);

        $upstreamHttpResponse = $upstreamHttpClient->request('DELETE', 'https://example.test/hooks/1');

        self::assertSame(204, $upstreamHttpResponse->statusCode());
        self::assertSame('', $upstreamHttpResponse->body());
        self::assertNull($upstreamHttpResponse->json());
    }

    #[Test]
    #[DataProvider('nonSuccessfulStatuses')]
    public function keepsHttpErrorResponsesAsResponses(int $statusCode): void
    {
        $upstreamHttpClient = $this->clientWithResponses([
            new Response($statusCode, [], '{"message":"error"}'),
        ]);

        $upstreamHttpResponse = $upstreamHttpClient->request('GET', 'https://example.test/api');

        self::assertSame($statusCode, $upstreamHttpResponse->statusCode());
        self::assertFalse($upstreamHttpResponse->isSuccessful());
        self::assertSame(404 === $statusCode, $upstreamHttpResponse->isNotFound());
        self::assertSame(401 === $statusCode || 403 === $statusCode, $upstreamHttpResponse->isAuthFailure());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonSuccessfulStatuses(): iterable
    {
        yield 'not found' => [404];

        yield 'unauthorized' => [401];

        yield 'forbidden' => [403];

        yield 'server error' => [500];
    }

    #[Test]
    public function invalidJsonReturnsNullJsonArray(): void
    {
        $upstreamHttpClient = $this->clientWithResponses([
            new Response(200, [], 'not json'),
        ]);

        $upstreamHttpResponse = $upstreamHttpClient->request('GET', 'https://example.test/api');

        self::assertNull($upstreamHttpResponse->json());
        self::assertNull($upstreamHttpResponse->jsonArray());
    }

    #[Test]
    public function convertsGuzzleTransportException(): void
    {
        $request            = new Request('GET', 'https://example.test/api');
        $upstreamHttpClient = $this->clientWithResponses([
            new ConnectException('Could not connect', $request),
        ]);

        $this->expectException(UpstreamHttpException::class);
        $this->expectExceptionMessage('Could not connect');

        $upstreamHttpClient->request('GET', 'https://example.test/api');
    }

    #[Test]
    public function sendsMethodHeadersTimeoutAndJsonBody(): void
    {
        $history            = [];
        $upstreamHttpClient = $this->clientWithResponses([
            new Response(201, [], '{"ok":true}'),
        ], $history);

        $upstreamHttpClient->request(
            'POST',
            'https://example.test/hooks',
            [
                'Authorization' => 'Bearer token',
                'X-Custom'      => ['one', 'two'],
            ],
            [
                'name' => 'packio',
            ],
            timeout: 7.5,
        );

        self::assertCount(1, $history);

        $transaction = $history[0];
        self::assertSame('POST', $transaction['request']->getMethod());
        self::assertSame('https://example.test/hooks', (string) $transaction['request']->getUri());
        self::assertSame('Bearer token', $transaction['request']->getHeaderLine('Authorization'));
        self::assertSame(['one', 'two'], $transaction['request']->getHeader('X-Custom'));
        self::assertSame(7.5, $transaction['options']['timeout']);
        self::assertSame(5.0, $transaction['options']['connect_timeout']);
        self::assertFalse($transaction['options']['http_errors']);
        self::assertSame([
            'name' => 'packio',
        ], json_decode((string) $transaction['request']->getBody(), true));
    }

    /**
     * @param list<ConnectException|Response> $responses
     * @param list<array<string, mixed>>      $history
     */
    private function clientWithResponses(array $responses, array &$history = []): UpstreamHttpClient
    {
        $mockHandler  = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::tap(
            static function(RequestInterface $request, array $options) use (&$history): void {
                $history[] = [
                    'request' => $request,
                    'options' => $options,
                ];
            },
        ));

        return new UpstreamHttpClient(new Client([
            'handler' => $handlerStack,
        ]));
    }
}
