<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Readme;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Gitlab\GitlabAccessCredentials;
use Packio\Publication\Infrastructure\Readme\Base64FileContentDecoder;
use Packio\Publication\Infrastructure\Readme\GitlabPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\MarkdownToHtmlRenderer;
use Packio\Publication\Infrastructure\Readme\ReadmeCandidateProvider;
use Packio\Publication\Infrastructure\Readme\ReadmeExtractorTemplate;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function array_map;
use function base64_encode;
use function is_string;
use function json_encode;

#[CoversClass(GitlabPackageReadmeExtractor::class)]
final class GitlabPackageReadmeExtractorTest extends TestCase
{
    #[Test]
    public function extractsReadmeFromGitlabContentsEndpoint(): void
    {
        $history                      = [];
        $gitlabPackageReadmeExtractor = $this->extractor([
            [
                'status' => 200,
                'body'   => [
                    'content' => base64_encode('# Packio'),
                ],
            ],
        ], $history);

        $html = $gitlabPackageReadmeExtractor->extractGitlabReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabAccessCredentials('gitlab-token'),
        );

        self::assertSame('<h1>Packio</h1>', $html);

        $request = $this->request($history, 0);
        self::assertSame(
            'https://gitlab.com/api/v4/projects/packio%2Fpackio/repository/files/README.md?ref=main',
            (string) $request->getUri(),
        );
        self::assertSame('PackioTest/1.0', $request->getHeaderLine('User-Agent'));
        self::assertSame('Bearer gitlab-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function returnsNullWhenGitlabReportsAllReadmeCandidatesAsMissing(): void
    {
        $history                      = [];
        $gitlabPackageReadmeExtractor = $this->extractor([
            [
                'status' => 404,
                'body'   => [
                    'message' => '404 File Not Found',
                ],
            ],
            [
                'status' => 404,
                'body'   => [
                    'message' => '404 File Not Found',
                ],
            ],
            [
                'status' => 404,
                'body'   => [
                    'message' => '404 File Not Found',
                ],
            ],
            [
                'status' => 404,
                'body'   => [
                    'message' => '404 File Not Found',
                ],
            ],
        ], $history);

        self::assertNull($gitlabPackageReadmeExtractor->extractGitlabReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabAccessCredentials('gitlab-token'),
        ));
        self::assertCount(4, $history);
    }

    #[Test]
    public function extractsEmptyStringWhenGitlabReadmeContentIsEmpty(): void
    {
        $html = $this->extractor([
            [
                'status' => 200,
                'body'   => [
                    'content' => '',
                ],
            ],
        ])->extractGitlabReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabAccessCredentials('gitlab-token'),
        );

        self::assertSame('', $html);
    }

    #[Test]
    public function throwsWhenGitlabReadmeFetchFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitLab README API error (500)');

        $this->extractor([
            [
                'status' => 500,
                'body'   => [
                    'message' => 'upstream error',
                ],
            ],
        ])->extractGitlabReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabAccessCredentials('gitlab-token'),
        );
    }

    #[Test]
    public function throwsWhenGitlabReadmeResponseIsInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitLab README API returned invalid JSON');

        $this->extractor([
            [
                'status' => 200,
                'body'   => 'not json',
            ],
        ])->extractGitlabReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabAccessCredentials('gitlab-token'),
        );
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function extractor(array $responses, array &$history = []): GitlabPackageReadmeExtractor
    {
        return new GitlabPackageReadmeExtractor(
            $this->httpClient($responses, $history),
            new BrandingConfig([
                'http_user_agent' => 'PackioTest/1.0',
            ]),
            new ReadmeExtractorTemplate(new ReadmeCandidateProvider(), new MarkdownToHtmlRenderer()),
            new Base64FileContentDecoder(),
            $this->storedSecretResolver(),
        );
    }

    private function storedSecretResolver(): StoredSecretResolver
    {
        return new StoredSecretResolver(new SecretEncryptorStub());
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function httpClient(array $responses, array &$history = []): UpstreamHttpClient
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

        return new UpstreamHttpClient(new Client([
            'handler' => $handlerStack,
        ]));
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
