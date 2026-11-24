<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Provider\SourceProviderMetadataCatalog;
use GuzzleHttp\Client;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Infrastructure\Facade\OAuthApiFacade;
use Packio\Identity\Infrastructure\OAuth\Client\BitbucketOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\CodebergOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitHubOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitLabOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthHttpClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthScopeResolver;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use Packio\Identity\Infrastructure\OAuth\OAuthClientConfigResolver;
use Packio\Identity\Infrastructure\OAuth\OAuthClientFactory;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function parse_str;
use function parse_url;

#[CoversClass(OAuthApiFacade::class)]
final class OAuthApiFacadeTest extends TestCase
{
    private const array INSTANCE_CONFIG = [
        'client_id'     => 'instance-client',
        'client_secret' => 'instance-secret',
        'redirect_url'  => 'https://app.test/auth/oauth/provider/source-callback',
        'base_url'      => 'https://git.example.test',
    ];

    #[Test]
    public function forgejoProviderInstanceAuthorizationUrlCarriesPkceChallenge(): void
    {
        $url = $this->facade()->getSourceAuthorizationUrlForProviderInstance('forgejo_self_hosted', self::INSTANCE_CONFIG, 'state', [], 'chal');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('chal', $query['code_challenge'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
    }

    #[Test]
    public function gitLabProviderInstanceAuthorizationUrlHasNoPkceChallengeWithoutOne(): void
    {
        $url = $this->facade()->getSourceAuthorizationUrlForProviderInstance('gitlab_self_hosted', self::INSTANCE_CONFIG, 'state', []);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertArrayNotHasKey('code_challenge', $query);
        self::assertArrayNotHasKey('code_challenge_method', $query);
    }

    private function facade(): OAuthApiFacade
    {
        $branding                 = new BrandingConfig([
            'http_user_agent' => 'PackioTest/1.0',
        ]);
        $oAuthHttpClient          = new OAuthHttpClient(new UpstreamHttpClient(new Client()));
        $oAuthScopeResolver       = new OAuthScopeResolver();
        $oAuthTokenResponseMapper = new OAuthTokenResponseMapper();

        return new OAuthApiFacade(
            new OAuthClientFactory(
                new GitHubOAuthClient($branding, [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
                new GitLabOAuthClient($branding, [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
                new BitbucketOAuthClient($branding, [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
                new CodebergOAuthClient($branding, [], $oAuthHttpClient, $oAuthScopeResolver, $oAuthTokenResponseMapper),
                $branding,
                new OAuthClientConfigResolver([]),
                $oAuthHttpClient,
                $oAuthScopeResolver,
                $oAuthTokenResponseMapper,
            ),
            new OAuthProviderAvailabilityResolver([], new SourceProviderMetadataCatalog(), $this->createStub(ProjectSettingsApiFacadeInterface::class)),
        );
    }
}
