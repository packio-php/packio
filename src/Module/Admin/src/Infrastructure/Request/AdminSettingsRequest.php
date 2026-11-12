<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Request;

final readonly class AdminSettingsRequest
{
    /**
     * @param array<string, string> $settings
     */
    public function __construct(public array $settings = []) {}
}
