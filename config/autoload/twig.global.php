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
        'extensions' => [
            ViteExtension::class,
            InertiaExtension::class,
        ],
    ],
];
