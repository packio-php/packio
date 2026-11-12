<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Provider;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Infrastructure\Provider\Bitbucket\HttpBitbucketProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\Codeberg\HttpCodebergProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\Github\HttpGithubProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\Gitlab\HttpGitlabProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function array_map;
use function is_string;
use function json_encode;

final class HttpProviderResourceFetcherContractTest extends TestCase
{
    #[Test]
    public function githubFetcherUsesTokenAndExpectedEndpoints(): void
    {
        $history                           = [];
        $httpGithubProviderResourceFetcher = new HttpGithubProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 200,
                    'body'   => [
                        [
                            'full_name'      => 'org/repo-one',
                            'html_url'       => 'https://github.com/org/repo-one',
                            'default_branch' => 'main',
                        ],
                    ],
                ],
                [
                    'status' => 200,
                    'body'   => [
                        [
                            'login' => 'org',
                        ],
                    ],
                ],
                [
                    'status' => 200,
                    'body'   => [
                        [
                            'full_name'      => 'org/repo-two',
                            'html_url'       => 'https://github.com/org/repo-two',
                            'default_branch' => 'main',
                        ],
                    ],
                ],
            ], $history),
        );

        $resources = $httpGithubProviderResourceFetcher->fetchResources('github-token');
        self::assertCount(2, $resources);

        self::assertCount(3, $history);
        self::assertSame('GET', $this->request($history[0])->getMethod());
        self::assertSame('https://api.github.com/user/repos?per_page=100&sort=updated&type=all', (string) $this->request($history[0])->getUri());
        self::assertSame('application/vnd.github+json', $this->request($history[0])->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $this->request($history[0])->getHeaderLine('User-Agent'));
        self::assertSame('Bearer github-token', $this->request($history[0])->getHeaderLine('Authorization'));

        self::assertSame('GET', $this->request($history[2])->getMethod());
        self::assertSame('https://api.github.com/orgs/org/repos?per_page=100&sort=updated&type=all', (string) $this->request($history[2])->getUri());
        self::assertSame('Bearer github-token', $this->request($history[2])->getHeaderLine('Authorization'));
    }

    #[Test]
    public function gitlabFetcherUsesTokenAndExpectedEndpoint(): void
    {
        $history                           = [];
        $httpGitlabProviderResourceFetcher = new HttpGitlabProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 200,
                    'body'   => [
                        [
                            'path_with_namespace' => 'org/repo-one',
                            'web_url'             => 'https://gitlab.com/org/repo-one',
                            'default_branch'      => 'main',
                            'visibility'          => 'public',
                        ],
                    ],
                ],
            ], $history),
        );

        $resources = $httpGitlabProviderResourceFetcher->fetchResources('gitlab-token');
        self::assertCount(1, $resources);

        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history[0])->getMethod());
        self::assertSame('https://gitlab.com/api/v4/projects?membership=true&per_page=100', (string) $this->request($history[0])->getUri());
        self::assertSame('application/json', $this->request($history[0])->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $this->request($history[0])->getHeaderLine('User-Agent'));
        self::assertSame('Bearer gitlab-token', $this->request($history[0])->getHeaderLine('Authorization'));
    }

    #[Test]
    public function bitbucketFetcherUsesTokenAndExpectedEndpoint(): void
    {
        $history                              = [];
        $httpBitbucketProviderResourceFetcher = new HttpBitbucketProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 200,
                    'body'   => [
                        'values' => [
                            [
                                'workspace' => [
                                    'slug' => 'workspace',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'status' => 200,
                    'body'   => [
                        'values' => [
                            [
                                'full_name'  => 'workspace/repo-one',
                                'links'      => [
                                    'html' => [
                                        'href' => 'https://bitbucket.org/workspace/repo-one',
                                    ],
                                ],
                                'mainbranch' => [
                                    'name' => 'develop',
                                ],
                                'is_private' => true,
                            ],
                        ],
                        'next'   => 'https://api.bitbucket.org/2.0/repositories?page=2',
                    ],
                ],
                [
                    'status' => 200,
                    'body'   => [
                        'values' => [
                            [
                                'full_name'  => 'workspace/repo-two',
                                'links'      => [],
                                'is_private' => false,
                            ],
                        ],
                    ],
                ],
            ], $history),
        );

        $resources = $httpBitbucketProviderResourceFetcher->fetchResources('bitbucket-token');
        self::assertCount(2, $resources);
        self::assertSame('workspace/repo-one', $resources[0]->id);
        self::assertSame('develop', $resources[0]->defaultBranch);
        self::assertTrue($resources[0]->private);
        self::assertSame('https://bitbucket.org/workspace/repo-two', $resources[1]->url);
        self::assertSame('main', $resources[1]->defaultBranch);

        self::assertCount(3, $history);
        self::assertSame('GET', $this->request($history[0])->getMethod());
        self::assertSame('https://api.bitbucket.org/2.0/user/workspaces?pagelen=100', (string) $this->request($history[0])->getUri());
        self::assertSame('application/json', $this->request($history[0])->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $this->request($history[0])->getHeaderLine('User-Agent'));
        self::assertSame('Bearer bitbucket-token', $this->request($history[0])->getHeaderLine('Authorization'));
        self::assertSame('https://api.bitbucket.org/2.0/repositories/workspace?role=member&pagelen=100', (string) $this->request($history[1])->getUri());
        self::assertSame('Bearer bitbucket-token', $this->request($history[1])->getHeaderLine('Authorization'));
        self::assertSame('https://api.bitbucket.org/2.0/repositories?page=2', (string) $this->request($history[2])->getUri());
    }

    #[Test]
    public function bitbucketFetcherClassifiesAuthFailures(): void
    {
        $httpBitbucketProviderResourceFetcher = new HttpBitbucketProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 403,
                    'body'   => [
                        'error' => [
                            'message' => 'Access token does not have repository scope.',
                        ],
                    ],
                ],
            ]),
        );

        $this->expectException(ProviderCallException::class);
        $this->expectExceptionMessage('provider_access_denied');

        $httpBitbucketProviderResourceFetcher->fetchResources('bitbucket-token');
    }

    #[Test]
    public function bitbucketFetcherKeepsNonAuthFailuresActionable(): void
    {
        $httpBitbucketProviderResourceFetcher = new HttpBitbucketProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 500,
                    'body'   => [
                        'error' => [
                            'message' => 'Upstream unavailable.',
                        ],
                    ],
                ],
            ]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider_unavailable');

        $httpBitbucketProviderResourceFetcher->fetchResources('bitbucket-token');
    }

    #[Test]
    public function codebergFetcherUsesTokenAndExpectedEndpoint(): void
    {
        $history                             = [];
        $httpCodebergProviderResourceFetcher = new HttpCodebergProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 200,
                    'body'   => [
                        [
                            'full_name'      => 'owner/repo-one',
                            'html_url'       => 'https://codeberg.org/owner/repo-one',
                            'default_branch' => 'trunk',
                            'description'    => 'Package repository',
                            'private'        => true,
                        ],
                    ],
                ],
            ], $history),
        );

        $resources = $httpCodebergProviderResourceFetcher->fetchResources('codeberg-token');

        self::assertCount(1, $resources);
        self::assertSame('owner/repo-one', $resources[0]->id);
        self::assertSame('codeberg', $resources[0]->type);
        self::assertSame('trunk', $resources[0]->defaultBranch);
        self::assertTrue($resources[0]->private);

        self::assertCount(1, $history);
        self::assertSame('GET', $this->request($history[0])->getMethod());
        self::assertSame('https://codeberg.org/api/v1/user/repos?page=1&limit=50', (string) $this->request($history[0])->getUri());
        self::assertSame('application/json', $this->request($history[0])->getHeaderLine('Accept'));
        self::assertSame('Packio/Tests', $this->request($history[0])->getHeaderLine('User-Agent'));
        self::assertSame('Bearer codeberg-token', $this->request($history[0])->getHeaderLine('Authorization'));
    }

    #[Test]
    public function codebergFetcherRetriesManualTokenAuthorizationOnAuthFailure(): void
    {
        $history                             = [];
        $httpCodebergProviderResourceFetcher = new HttpCodebergProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 401,
                    'body'   => [
                        'message' => 'Unauthorized',
                    ],
                ],
                [
                    'status' => 200,
                    'body'   => [],
                ],
            ], $history),
        );

        $resources = $httpCodebergProviderResourceFetcher->fetchResources('manual-codeberg-token');

        self::assertSame([], $resources);
        self::assertCount(2, $history);
        self::assertSame('Bearer manual-codeberg-token', $this->request($history[0])->getHeaderLine('Authorization'));
        self::assertSame('token manual-codeberg-token', $this->request($history[1])->getHeaderLine('Authorization'));
    }

    #[Test]
    public function codebergFetcherClassifiesAuthFailures(): void
    {
        $httpCodebergProviderResourceFetcher = new HttpCodebergProviderResourceFetcher(
            $this->brandingConfig(),
            $this->providerApiRequestor([
                [
                    'status' => 403,
                    'body'   => [
                        'message' => 'Forbidden',
                    ],
                ],
                [
                    'status' => 403,
                    'body'   => [
                        'message' => 'Token does not have access.',
                    ],
                ],
            ]),
        );

        $this->expectException(ProviderCallException::class);
        $this->expectExceptionMessage('provider_access_denied');

        $httpCodebergProviderResourceFetcher->fetchResources('codeberg-token');
    }

    private function brandingConfig(): BrandingConfig
    {
        return new BrandingConfig([
            'http_user_agent' => 'Packio/Tests',
        ]);
    }

    /**
     * @param list<array{status: int, body: mixed}> $responses
     * @param list<array<string, mixed>>            $history
     */
    private function providerApiRequestor(array $responses, array &$history = []): ProviderApiRequestor
    {
        return new ProviderApiRequestor($this->httpClient($responses, $history));
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
     * @param array<string, mixed> $transaction
     */
    private function request(array $transaction): RequestInterface
    {
        self::assertInstanceOf(RequestInterface::class, $transaction['request'] ?? null);

        return $transaction['request'];
    }
}
