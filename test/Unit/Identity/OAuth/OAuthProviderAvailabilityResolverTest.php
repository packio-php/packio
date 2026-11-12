<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Provider\SourceProviderMetadata;
use Common\Provider\SourceProviderMetadataCatalog;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Api\Dto\OAuthProviderAvailability;
use Packio\Identity\Api\OAuthProvider;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;

#[CoversClass(OAuthProviderAvailabilityResolver::class)]
#[CoversClass(OAuthProviderAvailability::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthProviderAvailabilityResolverTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    private const array DEFAULT_CONFIG = [
        'github'    => [
            'enabled'       => false,
            'client_id'     => 'github-client',
            'client_secret' => 'github-secret',
        ],
        'gitlab'    => [
            'enabled'       => false,
            'client_id'     => 'gitlab-client',
            'client_secret' => 'gitlab-secret',
        ],
        'bitbucket' => [
            'enabled'       => false,
            'client_id'     => 'bitbucket-client',
            'client_secret' => 'bitbucket-secret',
        ],
        'codeberg'  => [
            'enabled'       => false,
            'client_id'     => 'codeberg-client',
            'client_secret' => 'codeberg-secret',
        ],
    ];

    #[Test]
    public function allReturnsFourProviders(): void
    {
        $oAuthProviderAvailabilityResolver = $this->resolver(self::DEFAULT_CONFIG);
        $all                               = $oAuthProviderAvailabilityResolver->all();

        self::assertCount(4, $all);
        $providers = array_map(static fn (OAuthProviderAvailability $oAuthProviderAvailability): string => $oAuthProviderAvailability->provider, $all);
        self::assertContains('github', $providers);
        self::assertContains('gitlab', $providers);
        self::assertContains('bitbucket', $providers);
        self::assertContains('codeberg', $providers);
    }

    #[Test]
    public function allMatchesCatalogLoginOAuthBackedProviders(): void
    {
        $sourceProviderMetadataCatalog     = new SourceProviderMetadataCatalog();
        $oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver(
            self::DEFAULT_CONFIG,
            $sourceProviderMetadataCatalog,
            $this->fallbackProjectSettings(),
        );

        $expectedProviders = array_map(
            static fn (SourceProviderMetadata $sourceProviderMetadata): string => (string) $sourceProviderMetadata->oauthProvider,
            $sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders(),
        );
        $actualProviders = array_map(
            static fn (OAuthProviderAvailability $oAuthProviderAvailability): string => $oAuthProviderAvailability->provider,
            $oAuthProviderAvailabilityResolver->all(),
        );

        self::assertSame($expectedProviders, $actualProviders);
    }

    #[Test]
    public function bitbucketIsAvailableForAuthLogin(): void
    {
        $config                         = self::DEFAULT_CONFIG;
        $config['bitbucket']['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertSame(OAuthProvider::Bitbucket, $oAuthProviderAvailabilityResolver->resolveAuthProvider('bitbucket'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsAuthLogin('bitbucket'));

        $authProviders   = $oAuthProviderAvailabilityResolver->forAuthPages();
        $bitbucketOnAuth = array_filter(
            $authProviders,
            static fn (OAuthProviderAvailability $oAuthProviderAvailability): bool => 'bitbucket' === $oAuthProviderAvailability->provider,
        );
        self::assertCount(1, $bitbucketOnAuth);
    }

    #[Test]
    public function bitbucketIsAvailableForSourceConnect(): void
    {
        $config                         = self::DEFAULT_CONFIG;
        $config['bitbucket']['enabled'] = true;
        $config['bitbucket']['source']  = $this->sourceConfig();

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertSame(OAuthProvider::Bitbucket, $oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('bitbucket'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsSourceConnect('bitbucket'));
    }

    #[Test]
    public function bitbucketForSourceProviderMapsCorrectly(): void
    {
        $oAuthProviderAvailabilityResolver = $this->resolver(self::DEFAULT_CONFIG);

        $provider = $oAuthProviderAvailabilityResolver->forSourceProvider('bitbucket');
        self::assertInstanceOf(OAuthProviderAvailability::class, $provider);
        self::assertSame('bitbucket', $provider->provider);
    }

    #[Test]
    public function codebergIsAvailableForAuthLoginAndSourceConnect(): void
    {
        $config                        = self::DEFAULT_CONFIG;
        $config['codeberg']['enabled'] = true;
        $config['codeberg']['source']  = $this->sourceConfig();

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertSame(OAuthProvider::Codeberg, $oAuthProviderAvailabilityResolver->resolveAuthProvider('codeberg'));
        self::assertSame(OAuthProvider::Codeberg, $oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('codeberg'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsAuthLogin('codeberg'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsSourceConnect('codeberg'));
    }

    #[Test]
    public function disabledGitHubIsNotAvailableForAuthLogin(): void
    {
        $oAuthProviderAvailabilityResolver = $this->resolver(self::DEFAULT_CONFIG);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->isEnabled('github'));
    }

    #[Test]
    public function enabledGitHubIsAvailableForAuthLogin(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        $provider = $oAuthProviderAvailabilityResolver->resolveAuthProvider('github');
        self::assertInstanceOf(OAuthProvider::class, $provider);
        self::assertSame(OAuthProvider::Github, $provider);
        self::assertTrue($oAuthProviderAvailabilityResolver->isEnabled('github'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsAuthLogin('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->supportsSourceConnect('github'));
    }

    #[Test]
    public function enabledGitLabIsAvailableForAuthLogin(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['gitlab']['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        $provider = $oAuthProviderAvailabilityResolver->resolveAuthProvider('gitlab');
        self::assertInstanceOf(OAuthProvider::class, $provider);
        self::assertSame(OAuthProvider::Gitlab, $provider);
        self::assertTrue($oAuthProviderAvailabilityResolver->isEnabled('gitlab'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsAuthLogin('gitlab'));
        self::assertFalse($oAuthProviderAvailabilityResolver->supportsSourceConnect('gitlab'));
    }

    #[Test]
    public function unknownProviderReturnsNull(): void
    {
        $oAuthProviderAvailabilityResolver = $this->resolver(self::DEFAULT_CONFIG);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('unknown_provider'));
        self::assertNull($oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('unknown_provider'));
        self::assertNull($oAuthProviderAvailabilityResolver->forProvider('unknown_provider'));
        self::assertFalse($oAuthProviderAvailabilityResolver->isEnabled('unknown_provider'));
    }

    #[Test]
    public function resolveSourceConnectProviderRequiresSourceEnabled(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['github']['source']  = [
            'enabled'       => false,
            'client_id'     => 'source-client',
            'client_secret' => 'source-secret',
        ];

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github'));
    }

    #[Test]
    public function sourceConnectProviderDoesNotRequireLoginProviderToBeEnabled(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = false;
        $config['github']['source']  = $this->sourceConfig();

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertSame(OAuthProvider::Github, $oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->isEnabled('github'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsSourceConnect('github'));
    }

    #[Test]
    public function forceEnabledLoginProviderWithoutClientCredentialsIsNotAvailable(): void
    {
        $config                            = self::DEFAULT_CONFIG;
        $config['github']['enabled']       = true;
        $config['github']['client_id']     = '';
        $config['github']['client_secret'] = '';

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->isEnabled('github'));
    }

    #[Test]
    public function authLoginFollowsRuntimeOverride(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;

        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('booleanValue')
            ->willReturnCallback(static fn (string $key, bool $fallback): bool => 'oauth.github.enabled' === $key ? false : $fallback)
        ;

        $oAuthProviderAvailabilityResolver = $this->resolver($config, $settings);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->isEnabled('github'));
    }

    #[Test]
    public function sourceConnectFollowsRuntimeOverrideIndependentlyFromLoginOverride(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = false;
        $config['github']['source']  = $this->sourceConfig();

        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('booleanValue')
            ->willReturnCallback(static fn (string $key, bool $fallback): bool => match ($key) {
                'oauth.github.enabled'        => false,
                'oauth.github.source.enabled' => true,
                default                       => $fallback,
            })
        ;

        $oAuthProviderAvailabilityResolver = $this->resolver($config, $settings);

        self::assertNull($oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertSame(OAuthProvider::Github, $oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github'));
    }

    #[Test]
    public function gitLabSelfHostedSourceConnectUsesSelfHostedConfigFallback(): void
    {
        $config                          = self::DEFAULT_CONFIG;
        $config['gitlab']['self_hosted'] = [
            'source' => [
                'enabled' => true,
            ],
        ];
        $oAuthProviderAvailabilityResolver         = $this->resolver($config);
        $disabledOAuthProviderAvailabilityResolver = $this->resolver(self::DEFAULT_CONFIG);

        self::assertTrue($oAuthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled());
        self::assertFalse($disabledOAuthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled());
    }

    #[Test]
    public function gitLabSelfHostedSourceConnectFollowsRuntimeOverrideOverConfigFallback(): void
    {
        $config                          = self::DEFAULT_CONFIG;
        $config['gitlab']['self_hosted'] = [
            'source' => [
                'enabled' => true,
            ],
        ];

        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('booleanValue')
            ->willReturnCallback(static fn (string $key, bool $fallback): bool => match ($key) {
                'oauth.gitlab_self_hosted.source.enabled' => false,
                default                                   => $fallback,
            })
        ;

        $oAuthProviderAvailabilityResolver = $this->resolver($config, $settings);

        self::assertFalse($oAuthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled());
    }

    #[Test]
    public function enabledSourceConnectProviderAvailable(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['github']['source']  = $this->sourceConfig();

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        $provider = $oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github');
        self::assertInstanceOf(OAuthProvider::class, $provider);
        self::assertSame(OAuthProvider::Github, $provider);
    }

    #[Test]
    public function enabledLoginProviderWithoutSourceClientIsNotAvailableForSourceConnect(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['github']['source']  = [
            'enabled'       => true,
            'client_id'     => '',
            'client_secret' => '',
        ];

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertSame(OAuthProvider::Github, $oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertNull($oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github'));
        self::assertTrue($oAuthProviderAvailabilityResolver->supportsAuthLogin('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->supportsSourceConnect('github'));
    }

    #[Test]
    public function enabledLoginProviderWithDisabledSourceClientIsNotAvailableForSourceConnect(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['github']['source']  = [
            'enabled'       => false,
            'client_id'     => 'source-client',
            'client_secret' => 'source-secret',
        ];

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertSame(OAuthProvider::Github, $oAuthProviderAvailabilityResolver->resolveAuthProvider('github'));
        self::assertNull($oAuthProviderAvailabilityResolver->resolveSourceConnectProvider('github'));
        self::assertFalse($oAuthProviderAvailabilityResolver->supportsSourceConnect('github'));
    }

    #[Test]
    public function forAuthPagesReturnsVisibleProvidersWhenEnabled(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['gitlab']['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);
        $authProviders                     = $oAuthProviderAvailabilityResolver->forAuthPages();

        self::assertCount(4, $authProviders);
        $providerNames = array_map(
            static fn (OAuthProviderAvailability $oAuthProviderAvailability): string => $oAuthProviderAvailability->provider,
            $authProviders,
        );
        self::assertContains('github', $providerNames);
        self::assertContains('gitlab', $providerNames);
        self::assertContains('bitbucket', $providerNames);
        self::assertContains('codeberg', $providerNames);

        foreach ($authProviders as $authProvider) {
            self::assertTrue($authProvider->visibleOnAuthPages);
        }
    }

    #[Test]
    public function forAuthPagesReturnsVisibleProvidersRegardlessOfEnabled(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['gitlab']['enabled'] = false;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);
        $authProviders                     = $oAuthProviderAvailabilityResolver->forAuthPages();

        // forAuthPages filters by visibleOnAuthPages, not by enabled.
        self::assertCount(4, $authProviders);
        self::assertSame('github', $authProviders[0]->provider);
        self::assertTrue($authProviders[0]->enabled);
        self::assertSame('gitlab', $authProviders[1]->provider);
        self::assertFalse($authProviders[1]->enabled);
        self::assertSame('bitbucket', $authProviders[2]->provider);
        self::assertFalse($authProviders[2]->enabled);
        self::assertSame('codeberg', $authProviders[3]->provider);
        self::assertFalse($authProviders[3]->enabled);
    }

    #[Test]
    public function forSourceProviderMapsCorrectly(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;
        $config['github']['source']  = $this->sourceConfig();
        $config['gitlab']['enabled'] = true;
        $config['gitlab']['source']  = $this->sourceConfig();

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        $githubProvider = $oAuthProviderAvailabilityResolver->forSourceProvider('github');
        self::assertInstanceOf(OAuthProviderAvailability::class, $githubProvider);
        self::assertSame('github', $githubProvider->provider);

        $gitlabProvider = $oAuthProviderAvailabilityResolver->forSourceProvider('gitlab');
        self::assertInstanceOf(OAuthProviderAvailability::class, $gitlabProvider);
        self::assertSame('gitlab', $gitlabProvider->provider);

        $codebergProvider = $oAuthProviderAvailabilityResolver->forSourceProvider('codeberg');
        self::assertInstanceOf(OAuthProviderAvailability::class, $codebergProvider);
        self::assertSame('codeberg', $codebergProvider->provider);
    }

    #[Test]
    public function toAuthPagePayloadIncludesLabel(): void
    {
        $config                      = self::DEFAULT_CONFIG;
        $config['github']['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);
        $provider                          = $oAuthProviderAvailabilityResolver->forProvider('github');

        self::assertInstanceOf(OAuthProviderAvailability::class, $provider);
        $payload = $provider->toAuthPagePayload();
        self::assertSame('github', $payload['provider']);
        self::assertSame('GitHub', $payload['label']);
        self::assertTrue($payload['enabled']);
    }

    #[DataProvider('caseInsensitiveProviderNames')]
    #[Test]
    public function providerResolutionIsCaseInsensitive(string $input, string $expected): void
    {
        $config                       = self::DEFAULT_CONFIG;
        $config[$expected]['enabled'] = true;

        $oAuthProviderAvailabilityResolver = $this->resolver($config);

        self::assertTrue($oAuthProviderAvailabilityResolver->isEnabled($input));
        self::assertInstanceOf(OAuthProviderAvailability::class, $oAuthProviderAvailabilityResolver->forProvider($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function caseInsensitiveProviderNames(): iterable
    {
        return [
            'uppercase GitHub'     => ['GITHUB', 'github'],
            'mixed case GitHub'    => ['GitHub', 'github'],
            'uppercase GitLab'     => ['GITLAB', 'gitlab'],
            'mixed case GitLab'    => ['GitLab', 'gitlab'],
            'uppercase Bitbucket'  => ['BITBUCKET', 'bitbucket'],
            'mixed case Bitbucket' => ['Bitbucket', 'bitbucket'],
            'uppercase Codeberg'   => ['CODEBERG', 'codeberg'],
            'mixed case Codeberg'  => ['Codeberg', 'codeberg'],
        ];
    }

    /**
     * @return array{client_id: string, client_secret: string}
     */
    private function sourceConfig(): array
    {
        return [
            'enabled'       => true,
            'client_id'     => 'source-client',
            'client_secret' => 'source-secret',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolver(
        array $config,
        ?ProjectSettingsApiFacadeInterface $projectSettingsApiFacade = null
    ): OAuthProviderAvailabilityResolver {
        return new OAuthProviderAvailabilityResolver(
            $config,
            new SourceProviderMetadataCatalog(),
            $projectSettingsApiFacade ?? $this->fallbackProjectSettings(),
        );
    }
}
