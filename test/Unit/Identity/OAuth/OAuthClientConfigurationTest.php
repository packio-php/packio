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
use Packio\Identity\Api\Exception\OAuthRefreshRejectedException;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Infrastructure\OAuth\Client\BitbucketOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\CodebergOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitHubOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitLabOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthHttpClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthScopeResolver;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use Packio\Identity\Infrastructure\OAuth\OAuthClientConfigResolver;
use Packio\Identity\Infrastructure\OAuth\OAuthClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function array_map;
use function json_encode;
use function parse_str;
use function parse_url;

#[CoversClass(GitHubOAuthClient::class)]
#[CoversClass(GitLabOAuthClient::class)]
#[CoversClass(BitbucketOAuthClient::class)]
#[CoversClass(OAuthClientConfigResolver::class)]
#[CoversClass(OAuthClientFactory::class)]
final class OAuthClientConfigurationTest extends TestCase
{
    #[Test]
    public function githubAuthorizationUrlFallsBackWhenOverrideIsEmpty(): void
    {
        $gitHubOAuthClient = $this->gitHubClient([
            'client_id'         => 'github-client',
            'redirect_url'      => '/auth/oauth/github/callback',
            'authorization_url' => '',
        ]);

        $url = $gitHubOAuthClient->getAuthorizationUrl('state');

        self::assertStringStartsWith('https://github.com/login/oauth/authorize?', $url);
        self::assertStringContainsString('client_id=github-client', $url);
    }

    #[Test]
    public function gitlabAuthorizationUrlFallsBackWhenOverrideIsEmpty(): void
    {
        $gitLabOAuthClient = $this->gitLabClient([
            'client_id'         => 'gitlab-client',
            'redirect_url'      => '/auth/oauth/gitlab/callback',
            'base_url'          => 'https://gitlab.example.test',
            'authorization_url' => '',
        ]);

        $url = $gitLabOAuthClient->getAuthorizationUrl('state');

        self::assertStringStartsWith('https://gitlab.example.test/oauth/authorize?', $url);
        self::assertStringContainsString('client_id=gitlab-client', $url);
    }

    #[Test]
    public function gitlabSourceScopesIncludeApiAccessForWebhookRegistration(): void
    {
        $gitLabOAuthClient = $this->gitLabClient([]);

        self::assertSame(['read_user', 'api', 'read_repository'], $gitLabOAuthClient->sourceScopes());
    }

    #[Test]
    public function bitbucketAuthorizationUrlFallsBackWhenOverrideIsEmpty(): void
    {
        $bitbucketOAuthClient = $this->bitbucketClient([
            'client_id'         => 'bitbucket-client',
            'redirect_url'      => '/auth/oauth/bitbucket/callback',
            'authorization_url' => '',
        ]);

        $url = $bitbucketOAuthClient->getAuthorizationUrl('state');

        self::assertStringStartsWith('https://bitbucket.org/site/oauth2/authorize?', $url);
        self::assertStringContainsString('client_id=bitbucket-client', $url);
        self::assertStringContainsString('scope=account+email', $url);
    }

    #[Test]
    public function bitbucketSourceScopesIncludeRepositoryAndWebhookAccess(): void
    {
        $bitbucketOAuthClient = $this->bitbucketClient([]);

        self::assertSame(['account', 'email', 'repository', 'webhook'], $bitbucketOAuthClient->sourceScopes());
    }

    #[Test]
    public function sourceConfigDoesNotFallBackToLoginClientCredentialsOrRedirect(): void
    {
        $oAuthClientConfigResolver = new OAuthClientConfigResolver([
            'github' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/github/callback',
                'scopes'        => ['read:user'],
                'source'        => [
                    'client_id'            => '',
                    'client_secret'        => '',
                    'redirect_url'         => '',
                    'default_redirect_url' => 'https://app.test/auth/oauth/github/source-callback',
                    'scopes'               => [],
                ],
            ],
        ]);

        $config = $oAuthClientConfigResolver->sourceConfig('github');

        self::assertSame('', $config['client_id']);
        self::assertSame('', $config['client_secret']);
        self::assertSame('https://app.test/auth/oauth/github/source-callback', $config['redirect_url']);
        self::assertSame([], $config['scopes']);
        self::assertSame([], $config['source_scopes']);
    }

    #[Test]
    public function sourceConfigUsesSourceSpecificClientCredentialsRedirectAndScopes(): void
    {
        $oAuthClientConfigResolver = new OAuthClientConfigResolver([
            'github' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/github/callback',
                'scopes'        => ['read:user'],
                'source'        => [
                    'client_id'     => 'source-client',
                    'client_secret' => 'source-secret',
                    'redirect_url'  => 'https://app.test/auth/oauth/github/source-callback',
                    'scopes'        => ['read:org'],
                ],
            ],
        ]);

        $config = $oAuthClientConfigResolver->sourceConfig('github');

        self::assertSame('source-client', $config['client_id']);
        self::assertSame('source-secret', $config['client_secret']);
        self::assertSame('https://app.test/auth/oauth/github/source-callback', $config['redirect_url']);
        self::assertSame(['read:org'], $config['scopes']);
        self::assertSame(['read:org'], $config['source_scopes']);
    }

    #[Test]
    public function sourceConfigUsesDefaultSourceRedirectWhenSourceClientIsConfigured(): void
    {
        $oAuthClientConfigResolver = new OAuthClientConfigResolver([
            'codeberg' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/codeberg/callback',
                'source'        => [
                    'client_id'            => 'source-client',
                    'client_secret'        => 'source-secret',
                    'redirect_url'         => '',
                    'default_redirect_url' => 'https://app.test/auth/oauth/codeberg/source-callback',
                    'scopes'               => [],
                ],
            ],
        ]);

        $config = $oAuthClientConfigResolver->sourceConfig('codeberg');

        self::assertSame('source-client', $config['client_id']);
        self::assertSame('source-secret', $config['client_secret']);
        self::assertSame('https://app.test/auth/oauth/codeberg/source-callback', $config['redirect_url']);
    }

    #[Test]
    public function loginConfigIgnoresSourceSpecificOverrides(): void
    {
        $oAuthClientConfigResolver = new OAuthClientConfigResolver([
            'github' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/github/callback',
                'scopes'        => ['read:user'],
                'source'        => [
                    'client_id'     => 'source-client',
                    'client_secret' => 'source-secret',
                    'redirect_url'  => 'https://app.test/auth/oauth/github/source-callback',
                    'scopes'        => ['repo'],
                ],
            ],
        ]);

        $config = $oAuthClientConfigResolver->loginConfig('github');

        self::assertSame('login-client', $config['client_id']);
        self::assertSame('login-secret', $config['client_secret']);
        self::assertSame('https://app.test/auth/oauth/github/callback', $config['redirect_url']);
        self::assertSame(['read:user'], $config['scopes']);
        self::assertArrayNotHasKey('source', $config);
    }

    #[Test]
    public function factoryBuildsLoginAndSourceAuthorizationUrlsWithSeparateClients(): void
    {
        $factory = $this->factory([
            'github' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/github/callback',
                'scopes'        => ['read:user'],
                'source'        => [
                    'client_id'     => 'source-client',
                    'client_secret' => 'source-secret',
                    'redirect_url'  => 'https://app.test/auth/oauth/github/source-callback',
                    'scopes'        => ['repo', 'read:org'],
                ],
            ],
        ]);

        $loginUrl  = $factory->getAuthorizationUrl(OAuthProvider::Github, 'login-state');
        $sourceUrl = $factory->getSourceAuthorizationUrl(OAuthProvider::Github, 'source-state');

        parse_str((string) parse_url($loginUrl, PHP_URL_QUERY), $loginQuery);
        parse_str((string) parse_url($sourceUrl, PHP_URL_QUERY), $sourceQuery);

        self::assertSame('login-client', $loginQuery['client_id'] ?? null);
        self::assertSame('https://app.test/auth/oauth/github/callback', $loginQuery['redirect_uri'] ?? null);
        self::assertSame('read:user', $loginQuery['scope'] ?? null);

        self::assertSame('source-client', $sourceQuery['client_id'] ?? null);
        self::assertSame('https://app.test/auth/oauth/github/source-callback', $sourceQuery['redirect_uri'] ?? null);
        self::assertSame('repo,read:org', $sourceQuery['scope'] ?? null);
    }

    #[Test]
    public function factoryBuildsCodebergSourceAuthorizationUrlWithPkceAndSourceRedirect(): void
    {
        $factory = $this->factory([
            'codeberg' => [
                'client_id'     => 'login-client',
                'client_secret' => 'login-secret',
                'redirect_url'  => 'https://app.test/auth/oauth/codeberg/callback',
                'base_url'      => 'https://codeberg.org',
                'source'        => [
                    'client_id'     => 'source-client',
                    'client_secret' => 'source-secret',
                    'redirect_url'  => 'https://app.test/auth/oauth/codeberg/source-callback',
                    'scopes'        => [],
                ],
            ],
        ]);

        $sourceUrl = $factory->getSourceAuthorizationUrl(OAuthProvider::Codeberg, 'source-state', 'code-challenge');

        parse_str((string) parse_url($sourceUrl, PHP_URL_QUERY), $sourceQuery);

        self::assertSame('source-client', $sourceQuery['client_id'] ?? null);
        self::assertSame('https://app.test/auth/oauth/codeberg/source-callback', $sourceQuery['redirect_uri'] ?? null);
        self::assertSame('code-challenge', $sourceQuery['code_challenge'] ?? null);
        self::assertSame('S256', $sourceQuery['code_challenge_method'] ?? null);
        self::assertArrayNotHasKey('scope', $sourceQuery);
    }

    #[Test]
    public function factoryExchangesSourceCodeWithSourceClientAndRedirectUri(): void
    {
        $history = [];
        $factory = $this->factory(
            [
                'github' => [
                    'client_id'     => 'login-client',
                    'client_secret' => 'login-secret',
                    'redirect_url'  => 'https://app.test/auth/oauth/github/callback',
                    'source'        => [
                        'client_id'     => 'source-client',
                        'client_secret' => 'source-secret',
                        'redirect_url'  => 'https://app.test/auth/oauth/github/source-callback',
                    ],
                ],
            ],
            $this->httpClient([
                'access_token'  => 'source-access-token',
                'refresh_token' => 'source-refresh-token',
                'expires_in'    => 3600,
            ], $history),
        );

        $oAuthTokenResponse = $factory->exchangeSourceCodeForToken(OAuthProvider::Github, 'source-code');
        parse_str((string) $this->request($history, 0)->getBody(), $payload);

        self::assertSame('source-access-token', $oAuthTokenResponse->accessToken);
        self::assertSame('source-client', $payload['client_id'] ?? null);
        self::assertSame('source-secret', $payload['client_secret'] ?? null);
        self::assertSame('https://app.test/auth/oauth/github/source-callback', $payload['redirect_uri'] ?? null);
    }

    #[Test]
    public function codebergAndBitbucketClassifyRejectionOnlyForRefresh(): void
    {
        $body    = [
            'error'             => 'unauthorized_client',
            'error_description' => 'token was already used',
        ];
        $clients = fn (): array => [
            new CodebergOAuthClient($this->branding(), [], new OAuthHttpClient($this->httpClientQueue([$body])), new OAuthScopeResolver(), new OAuthTokenResponseMapper()),
            new BitbucketOAuthClient($this->branding(), [], new OAuthHttpClient($this->httpClientQueue([$body])), new OAuthScopeResolver(), new OAuthTokenResponseMapper()),
        ];

        foreach ($clients() as $client) {
            try {
                $client->refreshToken('refresh-token');
                self::fail('Expected OAuthRefreshRejectedException');
            } catch (OAuthRefreshRejectedException $oAuthRefreshRejectedException) {
                self::assertStringContainsString('(unauthorized_client): token was already used', $oAuthRefreshRejectedException->getMessage());
            }
        }

        foreach ($clients() as $client) {
            try {
                $client->exchangeCodeForToken('code');
                self::fail('Expected RuntimeException');
            } catch (RuntimeException $runtimeException) {
                self::assertNotInstanceOf(OAuthRefreshRejectedException::class, $runtimeException);
            }
        }
    }

    #[Test]
    public function gitlabIgnoresEmailWithoutConfirmation(): void
    {
        $unconfirmed = new GitLabOAuthClient($this->branding(), [], new OAuthHttpClient($this->httpClientQueue([
            [
                'id'           => 1,
                'email'        => 'x@example.test',
                'confirmed_at' => null,
            ],
        ])), new OAuthScopeResolver(), new OAuthTokenResponseMapper());
        $confirmed = new GitLabOAuthClient($this->branding(), [], new OAuthHttpClient($this->httpClientQueue([
            [
                'id'           => 1,
                'email'        => 'x@example.test',
                'confirmed_at' => '2024-01-01T00:00:00Z',
            ],
        ])), new OAuthScopeResolver(), new OAuthTokenResponseMapper());

        self::assertNull($unconfirmed->fetchUser('token')->email);
        self::assertSame('x@example.test', $confirmed->fetchUser('token')->email);
    }

    #[Test]
    public function bitbucketIgnoresPrimaryEmailWithoutConfirmationFlag(): void
    {
        $bitbucketOAuthClient = new BitbucketOAuthClient($this->branding(), [], new OAuthHttpClient($this->httpClientQueue([
            [
                'uuid'     => '{u}',
                'username' => 'u',
            ],
            [
                'values' => [
                    [
                        'email'      => 'x@example.test',
                        'is_primary' => true,
                    ],
                    [
                        'email'        => 'y@example.test',
                        'is_primary'   => true,
                        'is_confirmed' => 'yes',
                    ],
                ],
            ],
        ])), new OAuthScopeResolver(), new OAuthTokenResponseMapper());

        self::assertNull($bitbucketOAuthClient->fetchUser('token')->email);
    }

    private function branding(): BrandingConfig
    {
        return new BrandingConfig([
            'http_user_agent' => 'PackioTest/1.0',
        ]);
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
                unset($options);

                $history[] = [
                    'request' => $request,
                ];
            },
        ));

        return new UpstreamHttpClient(new Client([
            'handler' => $handlerStack,
        ]));
    }

    /**
     * @param array<string, mixed> $oauthConfig
     */
    private function factory(array $oauthConfig, ?UpstreamHttpClient $upstreamHttpClient = null): OAuthClientFactory
    {
        $upstreamHttpClient ??= $this->httpClient();
        $branding                 = $this->branding();
        $oAuthHttpClient          = new OAuthHttpClient($upstreamHttpClient);
        $oAuthScopeResolver       = new OAuthScopeResolver();
        $oAuthTokenResponseMapper = new OAuthTokenResponseMapper();

        return new OAuthClientFactory(
            new GitHubOAuthClient($branding, $oauthConfig['github'] ?? [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
            new GitLabOAuthClient($branding, $oauthConfig['gitlab'] ?? [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
            new BitbucketOAuthClient($branding, $oauthConfig['bitbucket'] ?? [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
            new CodebergOAuthClient($branding, $oauthConfig['codeberg'] ?? [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
            $branding,
            new OAuthClientConfigResolver($oauthConfig),
            $oAuthHttpClient,
            $oAuthScopeResolver,
            $oAuthTokenResponseMapper,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function gitHubClient(array $config): GitHubOAuthClient
    {
        return new GitHubOAuthClient($this->branding(), $config, new OAuthHttpClient($this->httpClient()), new OAuthScopeResolver(), new OAuthTokenResponseMapper());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function gitLabClient(array $config): GitLabOAuthClient
    {
        return new GitLabOAuthClient($this->branding(), $config, new OAuthHttpClient($this->httpClient()), new OAuthScopeResolver(), new OAuthTokenResponseMapper());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bitbucketClient(array $config): BitbucketOAuthClient
    {
        return new BitbucketOAuthClient($this->branding(), $config, new OAuthHttpClient($this->httpClient()), new OAuthScopeResolver(), new OAuthTokenResponseMapper());
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
