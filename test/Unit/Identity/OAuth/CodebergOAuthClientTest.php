<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use const JSON_THROW_ON_ERROR;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Identity\Infrastructure\OAuth\Client\CodebergOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthHttpClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthScopeResolver;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function array_map;
use function json_encode;
use function parse_str;
use function parse_url;

final class CodebergOAuthClientTest extends TestCase
{
    #[Test]
    public function buildsAuthorizationUrlWithForgejoEndpointsAndOptionalScopes(): void
    {
        $codebergOAuthClient = $this->client([
            'client_id'    => 'client-id',
            'redirect_url' => 'https://app.example/auth/oauth/codeberg/callback',
            'base_url'     => 'https://codeberg.org',
            'scopes'       => [],
        ]);

        $url = $codebergOAuthClient->getAuthorizationUrl('state-token');

        self::assertSame('https', parse_url($url, PHP_URL_SCHEME));
        self::assertSame('codeberg.org', parse_url($url, PHP_URL_HOST));
        self::assertSame('/login/oauth/authorize', parse_url($url, PHP_URL_PATH));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('client-id', $query['client_id'] ?? null);
        self::assertSame('https://app.example/auth/oauth/codeberg/callback', $query['redirect_uri'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('state-token', $query['state'] ?? null);
        self::assertArrayNotHasKey('scope', $query);
    }

    #[Test]
    public function buildsAuthorizationUrlWithPkceChallenge(): void
    {
        $codebergOAuthClient = $this->client([
            'client_id'    => 'client-id',
            'redirect_url' => 'https://app.example/auth/oauth/codeberg/callback',
            'base_url'     => 'https://codeberg.org',
            'scopes'       => [],
        ]);

        $url = $codebergOAuthClient->getAuthorizationUrlWithPkce('state-token', 'challenge-token');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('challenge-token', $query['code_challenge'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
    }

    #[Test]
    public function exchangeCodeForTokenSendsPkceVerifier(): void
    {
        $history             = [];
        $codebergOAuthClient = $this->client([
            'client_id'     => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_url'  => 'https://app.example/auth/oauth/codeberg/callback',
            'base_url'      => 'https://codeberg.org',
        ], $this->httpClient([
            'access_token'  => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in'    => 3600,
        ], $history));

        $oAuthTokenResponse = $codebergOAuthClient->exchangeCodeForToken('auth-code', 'verifier-token');

        parse_str((string) $this->request($history, 0)->getBody(), $payload);

        self::assertSame('access-token', $oAuthTokenResponse->accessToken);
        self::assertSame('auth-code', $payload['code'] ?? null);
        self::assertSame('verifier-token', $payload['code_verifier'] ?? null);
    }

    #[Test]
    public function codebergUsesOnlyVerifiedPrimaryEmail(): void
    {
        $history          = [];
        $oAuthUserData    = $this->client([
            'base_url' => 'https://codeberg.org',
        ], $this->httpClientQueue([
            [
                'id'    => 7,
                'login' => 'u',
                'email' => 'spoof@example.test',
            ],
            [
                [
                    'email'    => 'spoof@example.test',
                    'verified' => false,
                    'primary'  => true,
                ],
                [
                    'email'    => 'real@example.test',
                    'verified' => true,
                    'primary'  => false,
                ],
            ],
        ], $history))->fetchUser('token');

        self::assertSame('real@example.test', $oAuthUserData->email);
        self::assertSame('/api/v1/user/emails', $this->request($history, 1)->getUri()->getPath());
    }

    #[Test]
    public function codebergReturnsNullEmailWithoutVerifiedAddress(): void
    {
        $oAuthUserData = $this->client([
            'base_url' => 'https://codeberg.org',
        ], $this->httpClientQueue([
            [
                'id'    => 7,
                'login' => 'u',
                'email' => 'spoof@example.test',
            ],
            [[
                'email'    => 'spoof@example.test',
                'verified' => false,
                'primary'  => true,
            ]],
        ]))->fetchUser('token');

        self::assertNull($oAuthUserData->email);
    }

    #[Test]
    public function exposesConfiguredSourceScopes(): void
    {
        $codebergOAuthClient = $this->client([
            'source_scopes' => ['repo'],
        ]);

        self::assertSame(['repo'], $codebergOAuthClient->sourceScopes());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function client(array $config, ?UpstreamHttpClient $upstreamHttpClient = null): CodebergOAuthClient
    {
        return new CodebergOAuthClient(
            new BrandingConfig([
                'http_user_agent' => 'Packio/Tests',
            ]),
            $config,
            new OAuthHttpClient($upstreamHttpClient ?? $this->httpClient()),
            new OAuthScopeResolver(),
            new OAuthTokenResponseMapper(),
        );
    }

    /**
     * @param null|array<string, mixed>  $response
     * @param list<array<string, mixed>> $history
     */
    private function httpClient(?array $response = null, array &$history = []): UpstreamHttpClient
    {
        return $this->httpClientQueue(null === $response ? null : [$response], $history);
    }

    /**
     * @param null|list<array<int|string, mixed>> $responses JSON bodies served in order, each with status 200
     * @param list<array<string, mixed>>          $history
     */
    private function httpClientQueue(?array $responses, array &$history = []): UpstreamHttpClient
    {
        if (null === $responses) {
            return new UpstreamHttpClient(new Client());
        }

        $mockHandler = new MockHandler(array_map(
            static fn (array $body): Response => new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR)),
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
