<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Laminas\ServiceManager\ServiceManager;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

use function dirname;
use function sys_get_temp_dir;

/**
 * Real identity ObjectMapper (User -> UserDto); on-demand generation into a shared temp cache dir.
 */
final class IdentityObjectMapper
{
    public static function create(): ObjectMapper
    {
        $identityConfig = require dirname(__DIR__, 3) . '/src/Module/Identity/config/object-mapper.config.php';

        return (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                              => [
                    'object_mapper' => [
                        ...$identityConfig['object_mapper'],
                        'cache_dir'          => sys_get_temp_dir() . '/packio-test-identity-object-mapper',
                        'generate_on_demand' => true,
                        'transformers'       => [
                            UuidInterfaceToStringTransformer::class,
                            ChronosToAtomStringTransformer::class,
                            NullableChronosToAtomStringTransformer::class,
                            NullableChronosToNullableAtomStringTransformer::class,
                        ],
                    ],
                ],
                UuidInterfaceToStringTransformer::class               => new UuidInterfaceToStringTransformer(),
                ChronosToAtomStringTransformer::class                 => new ChronosToAtomStringTransformer(),
                NullableChronosToAtomStringTransformer::class         => new NullableChronosToAtomStringTransformer(),
                NullableChronosToNullableAtomStringTransformer::class => new NullableChronosToNullableAtomStringTransformer(),
            ],
        ]));
    }
}
