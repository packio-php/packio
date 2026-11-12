<?php

declare(strict_types=1);

return [
    'routing_attributes' => [
        'classes'                             => [],
        'duplicate_strategy'                  => 'throw', // throw|ignore
        'handlers'                            => [
            'mode' => 'psr15', // psr15|callable
        ],
        'override_mezzio_routes_list_command' => true,
        'route_list'                          => [
            'classic_routes_middleware_display' => 'upstream', // upstream|resolved
        ],
        'discovery'                           => [
            'enabled'  => true,
            'paths'    => ['src'],
            'strategy' => 'psr4', // token|psr4
            'psr4'     => [
                'mappings'          => [
                    'Common'               => 'src/Common/src/',
                    'Packio\Rest'          => 'src/Module/Rest/src/',
                    'Packio\Shared'        => 'src/Module/Shared/src/',
                    'Packio\Access'        => 'src/Module/Access/src/',
                    'Packio\Audit'         => 'src/Module/Audit/src/',
                    'Packio\Webhook'       => 'src/Module/Webhook/src/',
                    'Packio\Source'        => 'src/Module/Source/src/',
                    'Packio\Organization'  => 'src/Module/Organization/src/',
                    'Packio\Identity'      => 'src/Module/Identity/src/',
                    'Packio\Registry'      => 'src/Module/Registry/src/',
                    'Packio\Release'       => 'src/Module/Release/src/',
                    'Packio\Package'       => 'src/Module/Package/src/',
                    'Packio\Admin'         => 'src/Module/Admin/src/',
                    'Packio\Worker'        => 'src/Module/Worker/src/',
                    'Packio\Publication'   => 'src/Module/Publication/src/',
                    'Packio\Application'   => 'src/Application/src/',
                ],
                'fallback_to_token' => true,
            ],
        ],
        'cache'                               => [
            'enabled' => true,
            'file'    => 'data/cache/mezzio-routing-attributes.php',
        ],
    ],
];
