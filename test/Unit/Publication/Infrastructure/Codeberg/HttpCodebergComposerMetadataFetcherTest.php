<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Codeberg;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Publication\Infrastructure\Codeberg\CodebergAccessCredentials;
use Packio\Publication\Infrastructure\Codeberg\HttpCodebergComposerMetadataFetcher;
use Packio\Publication\Infrastructure\Git\GitCommandResult;
use Packio\Publication\Infrastructure\Git\GitCommandRunnerInterface;
use Packio\Publication\Infrastructure\Git\GitReference;
use Packio\Publication\Infrastructure\Git\GitRemoteReferenceReader;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use RuntimeException;

use function array_map;
use function base64_encode;
use function is_array;
use function is_string;
use function json_encode;
use function str_repeat;

#[CoversClass(HttpCodebergComposerMetadataFetcher::class)]
final class HttpCodebergComposerMetadataFetcherTest extends TestCase
{
    #[Test]
    public function fetchReferenceDoesNotListRemote(): void
    {
        $runner = $this->createMock(GitCommandRunnerInterface::class);
        $runner->expects(self::never())->method('run');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->with('GET', 'https://codeberg.org/api/v1/repos/team/repo/contents/composer.json?ref=v1.0.0', self::anything())->willReturn(new Response(200, [], json_encode([
            'content' => base64_encode('{"name":"team/repo"}'),
        ], JSON_THROW_ON_ERROR)));
        $httpCodebergComposerMetadataFetcher = new HttpCodebergComposerMetadataFetcher(new UpstreamHttpClient($client), new BrandingConfig([]), new StoredSecretResolver(new SecretEncryptorStub()), new GitRemoteReferenceReader($runner, OutboundPolicyFixture::git()));

        $codebergPackageMetadata = $httpCodebergComposerMetadataFetcher->fetchReference('team', 'repo', new GitReference('v1.0.0', str_repeat('b', 40), GitReference::TYPE_TAG), 'main');

        self::assertSame('team/repo', $codebergPackageMetadata->composer['name']);
        self::assertSame(str_repeat('b', 40), $codebergPackageMetadata->commitReference);
        self::assertSame('tag', $codebergPackageMetadata->refType);
        self::assertSame('main', $codebergPackageMetadata->defaultBranch);
        self::assertSame('v1.0.0', $codebergPackageMetadata->refName);
    }

    #[Test]
    public function fetchComposerJsonUsesContentsEndpointAndDecodesBase64Content(): void
    {
        $history                             = [];
        $httpCodebergComposerMetadataFetcher = $this->fetcher([
            [
                'status' => 200,
                'body'   => [
                    'content'  => base64_encode('{"name":"packio/packio"}'),
                    'encoding' => 'base64',
                ],
            ],
        ], $history);

        $metadata = $this->fetchComposerJson($httpCodebergComposerMetadataFetcher);

        self::assertSame('packio/packio', $metadata['name'] ?? null);

        $request = $this->request($history, 0);
        self::assertSame('https://codeberg.org/api/v1/repos/packio/packio/contents/composer.json?ref=main', (string) $request->getUri());
        self::assertSame('PackioTest/1.0', $request->getHeaderLine('User-Agent'));
        self::assertSame('Bearer codeberg-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function fetchComposerJsonReportsCodebergApiErrorsInsteadOfParsingThemAsComposerJson(): void
    {
        $httpCodebergComposerMetadataFetcher = $this->fetcher([
            [
                'status' => 404,
                'body'   => [
                    'message' => 'The target could not be found.',
                ],
            ],
            [
                'status' => 404,
                'body'   => [
                    'message' => 'The target could not be found.',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The target could not be found.');

        $this->fetchComposerJson($httpCodebergComposerMetadataFetcher);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchComposerJson(HttpCodebergComposerMetadataFetcher $httpCodebergComposerMetadataFetcher): array
    {
        $reflectionMethod = new ReflectionMethod($httpCodebergComposerMetadataFetcher, 'fetchComposerJson');

        $metadata = $reflectionMethod->invoke(
            $httpCodebergComposerMetadataFetcher,
            'packio',
            'packio',
            'main',
            new CodebergAccessCredentials('codeberg-token'),
        );
        if (! is_array($metadata)) {
            throw new RuntimeException('Expected Codeberg composer metadata to be an array.');
        }

        return $metadata;
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function fetcher(array $responses, array &$history = []): HttpCodebergComposerMetadataFetcher
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

        return new HttpCodebergComposerMetadataFetcher(
            new UpstreamHttpClient(new Client([
                'handler' => $handlerStack,
            ])),
            new BrandingConfig([
                'http_user_agent' => 'PackioTest/1.0',
            ]),
            $this->storedSecretResolver(),
            $this->gitRemoteReferenceReader(),
        );
    }

    private function storedSecretResolver(): StoredSecretResolver
    {
        return new StoredSecretResolver(new SecretEncryptorStub());
    }

    private function gitRemoteReferenceReader(): GitRemoteReferenceReader
    {
        return new GitRemoteReferenceReader(new class implements GitCommandRunnerInterface {
            public function run(string $command, array $environment = []): GitCommandResult
            {
                unset($command, $environment);

                return new GitCommandResult('', '', 0);
            }
        });
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
