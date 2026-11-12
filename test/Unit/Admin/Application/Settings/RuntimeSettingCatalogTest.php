<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Application\Settings;

use Common\Provider\SourceProviderMetadata;
use Common\Provider\SourceProviderMetadataCatalog;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingCatalog;
use Packio\Admin\Application\Settings\RuntimeSettingDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Seed\RuntimeSettingsCatalogSeed;

use function array_column;
use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

#[CoversClass(RuntimeSettingCatalog::class)]
#[CoversClass(RuntimeSettingDefinition::class)]
final class RuntimeSettingCatalogTest extends TestCase
{
    #[Test]
    public function apiDocumentationPublicSettingIsDefined(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());
        $definition            = $runtimeSettingCatalog->definition(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC);

        self::assertSame('Public API documentation', $definition->label);
        self::assertSame('api', $definition->group);
        self::assertSame('api.docs.public', $definition->configPath);
        self::assertTrue($definition->fallbackDefault);
    }

    #[Test]
    public function corsSettingIsDefined(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());
        $definition            = $runtimeSettingCatalog->definition(ProjectSettingsApiFacadeInterface::HTTP_CORS_ENABLED);

        self::assertSame('CORS', $definition->label);
        self::assertSame('http', $definition->group);
        self::assertSame('packio.cors.enabled', $definition->configPath);
        self::assertTrue($definition->fallbackDefault);
    }

    #[Test]
    public function runtimeSettingsCatalogSeedIsInSyncWithDefinitions(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());
        $catalogKeys           = $runtimeSettingCatalog->keys();
        $seedKeys              = $this->runtimeSettingsCatalogSeedKeys();

        self::assertSame([], array_values(array_diff($catalogKeys, $seedKeys)), 'Missing seed keys.');
        self::assertSame([], array_values(array_diff($seedKeys, $catalogKeys)), 'Unknown seed keys.');
    }

    #[Test]
    public function oauthLoginDefinitionsMatchCatalogBackedProviders(): void
    {
        $sourceProviderMetadataCatalog = new SourceProviderMetadataCatalog();
        $runtimeSettingCatalog         = new RuntimeSettingCatalog($sourceProviderMetadataCatalog);

        $expectedKeys = array_map(
            static fn (SourceProviderMetadata $sourceProviderMetadata): string => 'oauth.' . $sourceProviderMetadata->oauthProvider . '.enabled',
            $sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders(),
        );
        $actualDefinitions = $this->definitionsByGroup($runtimeSettingCatalog, 'oauth_login');

        self::assertSame($expectedKeys, array_column($actualDefinitions, 'key'));
    }

    #[Test]
    public function oauthLoginDefinitionsUseOAuthProviderAsProviderKey(): void
    {
        $sourceProviderMetadataCatalog = new SourceProviderMetadataCatalog();
        $runtimeSettingCatalog         = new RuntimeSettingCatalog($sourceProviderMetadataCatalog);

        foreach ($sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders() as $sourceProviderMetadata) {
            $definition = $runtimeSettingCatalog->definition('oauth.' . $sourceProviderMetadata->oauthProvider . '.enabled');

            self::assertSame($sourceProviderMetadata->oauthProvider, $definition->providerKey);
            self::assertSame('oauth.' . $sourceProviderMetadata->oauthProvider . '.enabled', $definition->configPath);
        }
    }

    #[Test]
    public function gitlabSelfHostedIsNotAnOAuthLoginSetting(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());

        self::assertFalse($runtimeSettingCatalog->has('oauth.gitlab_self_hosted.enabled'));

        foreach ($this->definitionsByGroup($runtimeSettingCatalog, 'oauth_login') as $runtimeSettingDefinition) {
            self::assertNotSame('gitlab_self_hosted', $runtimeSettingDefinition->providerKey);
        }
    }

    #[Test]
    public function sourceOAuthDefinitionsStillContainAllSourceProviderOAuthSettings(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());

        self::assertSame(
            [
                'oauth.github.source.enabled',
                'oauth.gitlab.source.enabled',
                'oauth.bitbucket.source.enabled',
                'oauth.codeberg.source.enabled',
                'oauth.gitlab_self_hosted.source.enabled',
            ],
            array_column($this->definitionsByGroup($runtimeSettingCatalog, 'source_oauth'), 'key'),
        );

        $runtimeSettingDefinition = $runtimeSettingCatalog->definition('oauth.gitlab_self_hosted.source.enabled');

        self::assertSame('gitlab_self_hosted', $runtimeSettingDefinition->providerKey);
        self::assertSame('oauth.gitlab.self_hosted.source.enabled', $runtimeSettingDefinition->configPath);
    }

    /**
     * @return list<RuntimeSettingDefinition>
     */
    private function definitionsByGroup(RuntimeSettingCatalog $runtimeSettingCatalog, string $group): array
    {
        return array_values(array_filter(
            $runtimeSettingCatalog->definitions(),
            static fn (RuntimeSettingDefinition $runtimeSettingDefinition): bool => $group === $runtimeSettingDefinition->group,
        ));
    }

    /**
     * @return list<string>
     */
    private function runtimeSettingsCatalogSeedKeys(): array
    {
        $reflectionConstant = new ReflectionClass(RuntimeSettingsCatalogSeed::class)
            ->getReflectionConstant('SETTING_KEYS')
        ;

        if (false === $reflectionConstant) {
            self::fail('RuntimeSettingsCatalogSeed::SETTING_KEYS must exist.');
        }

        $settingKeys = $reflectionConstant->getValue();

        if (! is_array($settingKeys)) {
            self::fail('RuntimeSettingsCatalogSeed::SETTING_KEYS must be an array.');
        }

        $stringKeys = [];
        foreach ($settingKeys as $settingKey) {
            if (! is_string($settingKey)) {
                self::fail('RuntimeSettingsCatalogSeed::SETTING_KEYS must contain strings only.');
            }

            $stringKeys[] = $settingKey;
        }

        return $stringKeys;
    }
}
