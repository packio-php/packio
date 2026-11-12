<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Application\Settings;

use Common\Provider\SourceProviderMetadataCatalog;
use InvalidArgumentException;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingCatalog;
use Packio\Admin\Application\Settings\RuntimeSettingDefinition;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Admin\Application\Settings\RuntimeSettingsService;
use Packio\Admin\Domain\Entity\ProjectSetting;
use Packio\Admin\Domain\Repository\ProjectSettingRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_flip;
use function array_keys;
use function array_values;
use function count;
use function strtolower;
use function trim;

#[CoversClass(RuntimeSettingCatalog::class)]
#[CoversClass(RuntimeSettingDefinition::class)]
#[CoversClass(RuntimeSettingOverride::class)]
#[CoversClass(RuntimeSettingsService::class)]
final class RuntimeSettingsServiceTest extends TestCase
{
    #[Test]
    public function missingSettingInheritsFallback(): void
    {
        $service = $this->service();

        self::assertTrue($service->booleanValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, true));
        self::assertFalse($service->booleanValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, false));
        self::assertSame(RuntimeSettingOverride::Inherit, $service->overrideValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
    }

    #[DataProvider('storedTriStateValues')]
    #[Test]
    public function storedTriStateValuesAreEvaluated(string $storedValue, bool $fallback, bool $expected): void
    {
        $inMemoryProjectSettingRepository = new InMemoryProjectSettingRepository([
            ProjectSetting::put(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, $storedValue),
        ]);
        $service = $this->service($inMemoryProjectSettingRepository);

        self::assertSame($expected, $service->booleanValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, $fallback));
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function storedTriStateValues(): iterable
    {
        yield 'inherit true' => [RuntimeSettingOverride::Inherit->value, true, true];

        yield 'inherit false' => [RuntimeSettingOverride::Inherit->value, false, false];

        yield 'on' => [RuntimeSettingOverride::On->value, false, true];

        yield 'off' => [RuntimeSettingOverride::Off->value, true, false];
    }

    #[DataProvider('legacyStoredBooleanValues')]
    #[Test]
    public function legacyStoredBooleanValuesAreRejected(string $storedValue): void
    {
        $inMemoryProjectSettingRepository = new InMemoryProjectSettingRepository([
            ProjectSetting::put(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, $storedValue),
        ]);
        $service = $this->service($inMemoryProjectSettingRepository);

        $this->expectException(InvalidArgumentException::class);

        $service->booleanValue(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN, false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyStoredBooleanValues(): iterable
    {
        yield '1' => ['1'];

        yield 'true' => ['true'];

        yield 'yes' => ['yes'];

        yield '0' => ['0'];

        yield 'false' => ['false'];

        yield 'no' => ['no'];

        yield 'empty string' => [''];
    }

    #[Test]
    public function updateOverridesPersistsTriStateValues(): void
    {
        $inMemoryProjectSettingRepository = new InMemoryProjectSettingRepository();
        $service                          = $this->service($inMemoryProjectSettingRepository);

        $service->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
        ], 'user-1');

        $setting = $inMemoryProjectSettingRepository->findByKey(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN);
        self::assertInstanceOf(ProjectSetting::class, $setting);
        self::assertSame(RuntimeSettingOverride::Off->value, $setting->value());
        self::assertSame('user-1', $setting->updatedByUserId());

        $service->updateOverrides([
            ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Inherit->value,
        ], 'user-2');

        self::assertSame(RuntimeSettingOverride::Inherit->value, $setting->value());
        self::assertSame('user-2', $setting->updatedByUserId());
    }

    #[Test]
    public function updateOverridesDoesNotPartiallyPersistInvalidPayload(): void
    {
        $inMemoryProjectSettingRepository = new InMemoryProjectSettingRepository();
        $service                          = $this->service($inMemoryProjectSettingRepository);
        $exception                        = null;

        try {
            $service->updateOverrides([
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
                'unknown.setting'                                    => RuntimeSettingOverride::On->value,
            ], 'user-1');
        } catch (InvalidArgumentException $exception) {
        }

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertNull($inMemoryProjectSettingRepository->findByKey(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
    }

    #[Test]
    public function updateOverridesDoesNotPartiallyPersistInvalidValue(): void
    {
        $inMemoryProjectSettingRepository = new InMemoryProjectSettingRepository();
        $service                          = $this->service($inMemoryProjectSettingRepository);
        $exception                        = null;

        try {
            $service->updateOverrides([
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
                'oauth.github.enabled'                               => 'enabled',
            ], 'user-1');
        } catch (InvalidArgumentException $exception) {
        }

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertNull($inMemoryProjectSettingRepository->findByKey(ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN));
        self::assertNull($inMemoryProjectSettingRepository->findByKey('oauth.github.enabled'));
    }

    #[Test]
    public function settingsPayloadUsesDefinitionDefaultForMissingConfigPath(): void
    {
        $service = $this->service();

        $payload = $service->settingsPayload([
            'identity' => [
                'registration_open' => false,
            ],
            'oauth'    => [],
        ]);

        self::assertFalse($payload['values'][ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN]['fallback']);
        self::assertFalse($payload['values'][ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN]['effective']);
        self::assertTrue($payload['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['fallback']);
        self::assertTrue($payload['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['effective']);
        self::assertTrue($payload['values']['source.providers.github.enabled']['fallback']);
        self::assertTrue($payload['values']['source.providers.github.enabled']['effective']);
        self::assertFalse($payload['values']['oauth.gitlab_self_hosted.source.enabled']['fallback']);
        self::assertFalse($payload['values']['oauth.gitlab_self_hosted.source.enabled']['effective']);
    }

    #[Test]
    public function settingsPayloadUsesApiDocsPublicConfigAsFallback(): void
    {
        $service = $this->service();

        $payload = $service->settingsPayload([
            'api' => [
                'docs' => [
                    'public' => false,
                ],
            ],
        ]);

        self::assertFalse($payload['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['fallback']);
        self::assertFalse($payload['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['effective']);
        self::assertSame(
            'api.docs.public',
            $payload['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['fallback_source'],
        );
    }

    #[Test]
    public function settingsPayloadUsesSourceProviderConfigAsFallback(): void
    {
        $service = $this->service();

        $payload = $service->settingsPayload([
            'sources' => [
                'providers' => [
                    'github' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);

        self::assertFalse($payload['values']['source.providers.github.enabled']['fallback']);
        self::assertFalse($payload['values']['source.providers.github.enabled']['effective']);
        self::assertSame(
            'sources.providers.github.enabled',
            $payload['values']['source.providers.github.enabled']['fallback_source'],
        );
    }

    #[Test]
    public function settingsPayloadUsesGitlabSelfHostedConfigAsSourceOAuthFallback(): void
    {
        $service = $this->service();

        $payload = $service->settingsPayload([
            'oauth' => [
                'gitlab' => [
                    'self_hosted' => [
                        'source' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($payload['values']['oauth.gitlab_self_hosted.source.enabled']['fallback']);
        self::assertTrue($payload['values']['oauth.gitlab_self_hosted.source.enabled']['effective']);
        self::assertSame(
            'oauth.gitlab.self_hosted.source.enabled',
            $payload['values']['oauth.gitlab_self_hosted.source.enabled']['fallback_source'],
        );
    }

    #[Test]
    public function unknownKeysAreRejected(): void
    {
        $service = $this->service();

        $this->expectException(InvalidArgumentException::class);

        $service->booleanValue('unknown.setting', true);
    }

    #[Test]
    public function itBuildsSettingsPayloadFromOneRepositoryRead(): void
    {
        $repository = $this->createMock(ProjectSettingRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([ProjectSetting::put('api.docs.public', 'off')]);
        $repository->expects(self::never())->method('findByKey');

        $payload = new RuntimeSettingsService($repository, new RuntimeSettingCatalog(new SourceProviderMetadataCatalog()))->settingsPayload([]);

        self::assertSame(
            [
                'override'        => 'off',
                'effective'       => false,
                'fallback'        => true,
                'fallback_source' => 'api.docs.public',
            ],
            $payload['values']['api.docs.public'],
        );
        self::assertSame('inherit', $payload['values']['identity.registration_open']['override']);
    }

    #[Test]
    public function catalogKeysAreUnique(): void
    {
        $runtimeSettingCatalog = new RuntimeSettingCatalog(new SourceProviderMetadataCatalog());

        self::assertSame(count($runtimeSettingCatalog->keys()), count(array_keys(array_flip($runtimeSettingCatalog->keys()))));
        self::assertContains('source.providers.github.enabled', $runtimeSettingCatalog->keys());
        self::assertContains('source.providers.gitlab_self_hosted.enabled', $runtimeSettingCatalog->keys());
        self::assertContains(ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC, $runtimeSettingCatalog->keys());
        self::assertContains('oauth.github.enabled', $runtimeSettingCatalog->keys());
        self::assertContains('oauth.github.source.enabled', $runtimeSettingCatalog->keys());
        self::assertContains('oauth.gitlab_self_hosted.source.enabled', $runtimeSettingCatalog->keys());
    }

    private function service(?InMemoryProjectSettingRepository $inMemoryProjectSettingRepository = null): RuntimeSettingsService
    {
        return new RuntimeSettingsService(
            $inMemoryProjectSettingRepository ?? new InMemoryProjectSettingRepository(),
            new RuntimeSettingCatalog(new SourceProviderMetadataCatalog()),
        );
    }
}

final class InMemoryProjectSettingRepository implements ProjectSettingRepositoryInterface
{
    /** @var array<string, ProjectSetting> */
    private array $settings = [];

    /**
     * @param list<ProjectSetting> $settings
     */
    public function __construct(array $settings = [])
    {
        foreach ($settings as $setting) {
            $this->settings[$this->normalizeKey($setting->key())] = $setting;
        }
    }

    public function save(ProjectSetting $projectSetting): ProjectSetting
    {
        $this->settings[$this->normalizeKey($projectSetting->key())] = $projectSetting;

        return $projectSetting;
    }

    public function findByKey(string $key): ?ProjectSetting
    {
        return $this->settings[$this->normalizeKey($key)] ?? null;
    }

    public function findAll(array $scope = [], array $orderBy = []): array
    {
        unset($scope, $orderBy);

        return array_values($this->settings);
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
