<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Registry\Infrastructure\DistArchiveStream;
use Packio\Registry\Infrastructure\GitlabSelfHostedDistArchiveFetcher;
use Packio\Registry\Infrastructure\HttpArchiveResponseGuard;
use Packio\Registry\Infrastructure\HttpDistArchiveDownloader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;

#[CoversClass(GitlabSelfHostedDistArchiveFetcher::class)]
#[CoversClass(DistArchiveStream::class)]
#[CoversClass(HttpArchiveResponseGuard::class)]
final class GitlabSelfHostedDistArchiveFetcherTest extends TestCase
{
    #[Test]
    public function itFetchesSelfHostedGitlabArchiveFromApiEndpoint(): void
    {
        $history                            = [];
        $gitlabSelfHostedDistArchiveFetcher = $this->fetcher([
            [
                'status' => 200,
                'body'   => 'zip-content',
            ],
        ], $history);

        $distArchiveStream = $gitlabSelfHostedDistArchiveFetcher->fetchArchive(
            'https://git.example.test',
            'group/subgroup',
            'package',
            'feature/Action',
            'source-token',
            'oauth2',
        );

        self::assertSame('zip-content', $distArchiveStream->body->getContents());

        self::assertCount(1, $history);
        $request = $this->request($history, 0);
        self::assertSame(
            'https://git.example.test/api/v4/projects/group%2Fsubgroup%2Fpackage/repository/archive.zip?sha=feature%2FAction',
            (string) $request->getUri(),
        );
        self::assertSame('application/zip', $request->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $request->getHeaderLine('User-Agent'));
        self::assertSame('Bearer source-token', $request->getHeaderLine('Authorization'));
        self::assertSame('', $request->getHeaderLine('PRIVATE-TOKEN'));
    }

    #[Test]
    public function itFetchesSelfHostedGitlabArchiveWithPrivateTokenHeaderForManualTokenSources(): void
    {
        $history                            = [];
        $gitlabSelfHostedDistArchiveFetcher = $this->fetcher([
            [
                'status' => 200,
                'body'   => 'zip-content',
            ],
        ], $history);

        $distArchiveStream = $gitlabSelfHostedDistArchiveFetcher->fetchArchive(
            'https://git.example.test',
            'group',
            'package',
            'main',
            'manual-token',
            'token',
        );

        self::assertSame('zip-content', $distArchiveStream->body->getContents());

        self::assertCount(1, $history);
        self::assertSame('', $this->request($history, 0)->getHeaderLine('Authorization'));
        self::assertSame('manual-token', $this->request($history, 0)->getHeaderLine('PRIVATE-TOKEN'));
    }

    /**
     * @param list<array{status: int, body: string}> $responses
     * @param list<array<string, mixed>>             $history
     */
    private function fetcher(array $responses, array &$history = []): GitlabSelfHostedDistArchiveFetcher
    {
        $mockHandler = new MockHandler(array_map(
            static fn (array $response): Response => new Response($response['status'], [], $response['body']),
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

        return new GitlabSelfHostedDistArchiveFetcher(
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
