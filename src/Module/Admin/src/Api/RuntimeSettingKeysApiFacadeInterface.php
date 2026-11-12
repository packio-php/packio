<?php

declare(strict_types=1);

namespace Packio\Admin\Api;

interface RuntimeSettingKeysApiFacadeInterface
{
    /** @return list<string> */
    public function settingKeys(): array;
}
