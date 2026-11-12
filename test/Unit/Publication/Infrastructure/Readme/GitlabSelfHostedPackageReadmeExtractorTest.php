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
use Packio\Publication\Infrastructure\GitlabSelfHosted\GitlabSelfHostedAccessCredentials;
use Packio\Publication\Infrastructure\Readme\GitlabSelfHostedPackageReadmeExtractor;
use Packio\Publication\Infrastructure\Readme\MarkdownToHtmlRenderer;
use Packio\Publication\Infrastructure\Readme\ReadmeCandidateProvider;
use Packio\Publication\Infrastructure\Readme\ReadmeExtractorTemplate;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function is_string;
use function json_encode;

#[CoversClass(GitlabSelfHostedPackageReadmeExtractor::class)]
final class GitlabSelfHostedPackageReadmeExtractorTest extends TestCase
{
    #[Test]
    public function extractsReadmeFromSelfHostedGitlabRawEndpoint(): void
    {
        $history                                = [];
        $gitlabSelfHostedPackageReadmeExtractor = $this->extractor([
            [
                'status' => 200,
                'body'   => '# Packio',
            ],
        ], $history);

        $html = $gitlabSelfHostedPackageReadmeExtractor->extractGitlabSelfHostedReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabSelfHostedAccessCredentials('https://gitlab.company.test', 'gitlab-token'),
        );

        self::assertSame('<h1>Packio</h1>', $html);

        $request = $this->request($history, 0);
        self::assertSame(
            'https://gitlab.company.test/api/v4/projects/packio%2Fpackio/repository/files/README.md/raw?ref=main',
            (string) $request->getUri(),
        );
        self::assertSame('PackioTest/1.0', $request->getHeaderLine('User-Agent'));
        self::assertSame('Bearer gitlab-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function fallsBackToPrivateTokenForSelfHostedGitlabReadme(): void
    {
        $history                                = [];
        $gitlabSelfHostedPackageReadmeExtractor = $this->extractor([
            [
                'status' => 401,
                'body'   => [
                    'message' => 'Unauthorized',
                ],
            ],
            [
                'status' => 200,
                'body'   => '# Packio',
            ],
        ], $history);

        $html = $gitlabSelfHostedPackageReadmeExtractor->extractGitlabSelfHostedReadmeHtml(
            'packio',
            'packio',
            'main',
            new GitlabSelfHostedAccessCredentials('https://gitlab.company.test', 'manual-token'),
        );

        self::assertSame('<h1>Packio</h1>', $html);

        self::assertCount(2, $history);
        self::assertSame('Bearer manual-token', $this->request($history, 0)->getHeaderLine('Authorization'));
        self::assertSame('manual-token', $this->request($history, 1)->getHeaderLine('PRIVATE-TOKEN'));
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function extractor(array $responses, array &$history = []): GitlabSelfHostedPackageReadmeExtractor
    {
        return new GitlabSelfHostedPackageReadmeExtractor(
            $this->httpClient($responses, $history),
            new BrandingConfig([
                'http_user_agent' => 'PackioTest/1.0',
            ]),
            new ReadmeExtractorTemplate(new ReadmeCandidateProvider(), new MarkdownToHtmlRenderer()),
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
