<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Facade;

use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingCatalog;

final readonly class RuntimeSettingKeysApiFacade implements RuntimeSettingKeysApiFacadeInterface
{
    public function __construct(private RuntimeSettingCatalog $runtimeSettingCatalog) {}

    public function settingKeys(): array
    {
        return $this->runtimeSettingCatalog->keys();
    }
}
