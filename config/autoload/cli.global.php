<?php

declare(strict_types=1);

/**
 * Project-level CLI commands and overrides only.
 *
 * Module-owned commands are registered in each module's own config/cli.config.php
 * and are automatically loaded via the module's ConfigProvider.
 *
 * Examples of what belongs here:
 *   - commands that orchestrate multiple modules;
 *   - aliases or composite commands;
 *   - dev/tooling commands for the application itself;
 *   - temporary migration commands.
 */

return [
    'laminas-cli' => [
        'commands' => [
            // No project-level commands yet.
        ],
    ],
];
