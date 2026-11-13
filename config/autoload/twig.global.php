<?php

declare(strict_types=1);

use Sirix\InertiaPsr15\Twig\InertiaExtension;
use Sirix\TwigViteExtension\Twig\ViteExtension;

return [
    'templates' => [
        'extension' => 'twig',
        'paths'     => [
            '__main__' => ['templates'],
            'errors'   => ['templates/errors'],
        ],
    ],
    'twig'      => [
        'cache_dir'  => 'data/cache/twig',
        // Pages rendered outside Inertia (Mezzio's 404) get no branding from a handler.
        'globals'    => [
            'branding' => [
                'name'            => \env('BRAND_PRODUCT_NAME', 'Packio'),
                'domain'          => \env('BRAND_DOMAIN', 'packio.localhost'),
                'themeStorageKey' => \env('BRAND_THEME_STORAGE_KEY', 'packio-theme'),
            ],
        ],
        'extensions' => [
            ViteExtension::class,
            InertiaExtension::class,
        ],
    ],
];
