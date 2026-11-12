<?php

declare(strict_types=1);

namespace Packio\Application;

use function loadConfigFromGlob;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return loadConfigFromGlob(__DIR__ . '/../config/{,*.}config.php');
    }
}
