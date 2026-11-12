<?php

declare(strict_types=1);

namespace Seed;

use Sirix\Cycle\Service\SeedInterface;
use Cycle\Database\DatabaseInterface;

final class RuntimeSettingsCatalogSeed implements SeedInterface
{
    public const string DATABASE = 'default';

    private const string TABLE = 'project_settings';

    /**
     * Keep this list in sync with Packio\Admin\Application\Settings\RuntimeSettingCatalog.
     *
     * @var list<string>
     */
    private const array SETTING_KEYS = [
        'identity.registration_open',
        'api.docs.public',
        'http.cors.enabled',
        'source.providers.github.enabled',
        'source.providers.gitlab.enabled',
        'source.providers.gitlab_self_hosted.enabled',
        'source.providers.generic_git.enabled',
        'source.providers.bitbucket.enabled',
        'source.providers.codeberg.enabled',
        'oauth.github.enabled',
        'oauth.gitlab.enabled',
        'oauth.bitbucket.enabled',
        'oauth.codeberg.enabled',
        'oauth.github.source.enabled',
        'oauth.gitlab.source.enabled',
        'oauth.bitbucket.source.enabled',
        'oauth.codeberg.source.enabled',
        'oauth.gitlab_self_hosted.source.enabled',
    ];

    private DatabaseInterface $database;

    public function run(): void
    {
        foreach (self::SETTING_KEYS as $key) {
            if ($this->settingExists($key)) {
                continue;
            }

            $this->database
                ->insert(self::TABLE)
                ->values([
                    'setting_key'   => $key,
                    'setting_value' => 'inherit',
                ])
                ->run()
            ;
        }
    }

    private function settingExists(string $key): bool
    {
        $rows = $this->database
            ->select('setting_key')
            ->from(self::TABLE)
            ->where('setting_key', $key)
            ->limit(1)
            ->fetchAll()
        ;

        return [] !== $rows;
    }
}
