<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Registry\Infrastructure\CodebergDistArchiveFetcher;
use Packio\Registry\Infrastructure\DistArchiveStream;
use Packio\Registry\Infrastructure\HttpArchiveResponseGuard;
use Packio\Registry\Infrastructure\HttpDistArchiveDownloader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function is_string;
use function json_encode;

#[CoversClass(CodebergDistArchiveFetcher::class)]
#[CoversClass(DistArchiveStream::class)]
#[CoversClass(HttpArchiveResponseGuard::class)]
final class CodebergDistArchiveFetcherTest extends TestCase
{
    #[Test]
    public function itFetchesCodebergArchiveFromApiEndpoint(): void
    {
        $history                    = [];
        $codebergDistArchiveFetcher = $this->fetcher([
            [
                'status' => 200,
                'body'   => 'zip-content',
            ],
        ], $history);

        $distArchiveStream = $codebergDistArchiveFetcher->fetchArchive('vendor', 'package', 'v1.0.0', 'codeberg-token');

        self::assertSame('zip-content', $distArchiveStream->body->getContents());

        self::assertCount(1, $history);
        $request = $this->request($history, 0);
        self::assertSame('https://codeberg.org/api/v1/repos/vendor/package/archive/v1.0.0.zip', (string) $request->getUri());
        self::assertSame('application/octet-stream', $request->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $request->getHeaderLine('User-Agent'));
        self::assertSame('Bearer codeberg-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function itRetriesWithTokenAuthorizationForManualTokens(): void
    {
        $history                    = [];
        $codebergDistArchiveFetcher = $this->fetcher([
            [
                'status' => 401,
                'body'   => [
                    'message' => 'unauthorized',
                ],
            ],
            [
                'status' => 200,
                'body'   => 'zip-content',
            ],
        ], $history);

        $distArchiveStream = $codebergDistArchiveFetcher->fetchArchive('vendor', 'package', 'v1.0.0', 'codeberg-token');

        self::assertSame('zip-content', $distArchiveStream->body->getContents());

        self::assertCount(2, $history);
        self::assertSame('Bearer codeberg-token', $this->request($history, 0)->getHeaderLine('Authorization'));
        self::assertSame('token codeberg-token', $this->request($history, 1)->getHeaderLine('Authorization'));
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function fetcher(array $responses, array &$history = []): CodebergDistArchiveFetcher
    {
        $mockHandler = new MockHandler(array_map(
            fn (array $response): Response => new Response(
                $response['status'],
                [],
                is_string($response['body']) ? $response['body'] : json_encode($response['body'], JSON_THROW_ON_ERROR),
            ),
            $responses,
        ));
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::tap(
            static function(RequestInterface $request, array $options) use (&$history): void {
                $history[] = [
                    'request' => $request,
                    'options' => $options,
                ];
            },
        ));

        return new CodebergDistArchiveFetcher(
            new BrandingConfig([
                'http_user_agent' => 'Packio/Tests',
            ]),
            new HttpDistArchiveDownloader(new UpstreamHttpClient(new Client([
                'handler' => $handlerStack,
            ]))),
        );
    }

    /**
     * @param list<array<string, mixed>> $history
     */
    private function request(array $history, int $index): RequestInterface
    {
        self::assertInstanceOf(RequestInterface::class, $history[$index]['request'] ?? null);

        return $history[$index]['request'];
    }
}
