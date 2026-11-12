<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\ObjectMapper;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Cycle\ORM\EntityProxyInterface;
use Laminas\ServiceManager\ServiceManager;
use Packio\Source\Api\Dto\SourceProviderInstanceDependenciesDto;
use Packio\Source\Api\Dto\WebhookRegistrationResultDto;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Dto\SourceProviderInstanceDependencies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ObjectMapper\Exception\MappingCompilationFailed;
use Sirix\ObjectMapper\Exception\MappingExecutionFailed;
use Sirix\ObjectMapper\Exception\MappingNotRegistered;
use Sirix\ObjectMapper\Runtime\ObjectMapper;
use stdClass;

use function array_map;
use function bin2hex;
use function chmod;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function symlink;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(ObjectMapperFactory::class)]
final class ObjectMapperFactoryTest extends TestCase
{
    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory) {
            return;
        }

        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([
            ...(glob($formatCacheDirectory . '/*') ?: []),
            ...(glob($formatCacheDirectory . '/.*') ?: []),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
    }

    #[Test]
    public function itBuildsAndWarmsRegisteredMappings(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-object-mapper-' . bin2hex(random_bytes(8));

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config' => [
                    'object_mapper' => [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'mappings'           => [
                            SourceProviderInstanceDependencies::class . '->' . SourceProviderInstanceDependenciesDto::class => [
                                'source'         => SourceProviderInstanceDependencies::class,
                                'target'         => SourceProviderInstanceDependenciesDto::class,
                                'rules'          => [],
                                'ignored_source' => [],
                            ],
                            WebhookRegistrationResult::class . '->' . WebhookRegistrationResultDto::class                   => [
                                'source'         => WebhookRegistrationResult::class,
                                'target'         => WebhookRegistrationResultDto::class,
                                'rules'          => [],
                                'ignored_source' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        self::assertInstanceOf(ObjectMapper::class, $objectMapper);
        self::assertSame([
            WebhookRegistrationResult::class . '->' . WebhookRegistrationResultDto::class,
            SourceProviderInstanceDependencies::class . '->' . SourceProviderInstanceDependenciesDto::class,
        ], $objectMapper->warmup());

        $sourceProviderInstanceDependenciesDto = $objectMapper->map(
            new SourceProviderInstanceDependencies(
                instanceId: 'instance-1',
                label: 'GitHub',
                baseUrl: 'https://github.com',
                providerType: 'github',
                sources: [[
                    'id'       => 'source-1',
                    'name'     => 'packio',
                    'url'      => 'https://github.com/packio-php/packio',
                    'provider' => 'github',
                    'status'   => 'active',
                ]],
                authorizations: [[
                    'id'           => 'authorization-1',
                    'accountName'  => 'packio',
                    'provider'     => 'github',
                    'authStrategy' => 'oauth',
                    'status'       => 'active',
                ]],
            ),
            SourceProviderInstanceDependenciesDto::class,
        );

        self::assertSame('instance-1', $sourceProviderInstanceDependenciesDto->instanceId);
        self::assertSame(1, $sourceProviderInstanceDependenciesDto->activeAuthorizationCount());

        $webhookRegistrationResultDto = $objectMapper->map(
            new WebhookRegistrationResult(
                success: true,
                externalId: 'hook-1',
                message: 'Webhook is registered.',
                alreadyExists: false,
                authHealth: 'healthy',
            ),
            WebhookRegistrationResultDto::class,
        );

        self::assertTrue($webhookRegistrationResultDto->success);
        self::assertSame('hook-1', $webhookRegistrationResultDto->externalId);
        self::assertSame('healthy', $webhookRegistrationResultDto->authHealth);
    }

    #[Test]
    public function itCompilesStructuralAndTransformerRulesUsingSharedRegistries(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-object-mapper-' . bin2hex(random_bytes(8));
        $objectMapper         = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                => [
                    'object_mapper' => [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => [UuidInterfaceToStringTransformer::class, ChronosToAtomStringTransformer::class],
                        'mappings'           => [
                            MapperTestChild::class . '->' . MapperTestChildDto::class   => [
                                'source' => MapperTestChild::class,
                                'target' => MapperTestChildDto::class,
                                'rules'  => [
                                    'id' => [
                                        'property'    => 'id',
                                        'transformer' => UuidInterfaceToStringTransformer::class,
                                    ],
                                ],
                            ],
                            MapperTestParent::class . '->' . MapperTestParentDto::class => [
                                'source' => MapperTestParent::class,
                                'target' => MapperTestParentDto::class,
                                'rules'  => [
                                    'child' => [
                                        'property' => 'child',
                                        'nested'   => MapperTestChildDto::class,
                                    ],
                                    'at'    => [
                                        'method'      => 'at',
                                        'transformer' => ChronosToAtomStringTransformer::class,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                UuidInterfaceToStringTransformer::class => new UuidInterfaceToStringTransformer(),
                ChronosToAtomStringTransformer::class   => new ChronosToAtomStringTransformer(),
            ],
        ]));

        self::assertSame([
            MapperTestChild::class . '->' . MapperTestChildDto::class,
            MapperTestParent::class . '->' . MapperTestParentDto::class,
        ], $objectMapper->warmup());

        $mapperTestParentDto = $objectMapper->map(
            new MapperTestParent(new MapperTestChild(Uuid::fromString('f4f98980-2d12-4db6-89ba-75274400d5c1')), Chronos::parse('2026-08-29T12:34:56+03:00')),
            MapperTestParentDto::class,
        );

        self::assertSame('f4f98980-2d12-4db6-89ba-75274400d5c1', $mapperTestParentDto->child->id);
        self::assertSame('2026-08-29T12:34:56+03:00', $mapperTestParentDto->at);
    }

    #[Test]
    public function itCompilesCollectionRulesWithUnorderedCollectionConfigKeys(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestCollectionItem::class . '->' . MapperTestCollectionItemDto::class     => [
                'source' => MapperTestCollectionItem::class,
                'target' => MapperTestCollectionItemDto::class,
                'rules'  => [],
            ],
            MapperTestCollectionParent::class . '->' . MapperTestCollectionParentDto::class => [
                'source' => MapperTestCollectionParent::class,
                'target' => MapperTestCollectionParentDto::class,
                'rules'  => [
                    'items' => [
                        'property'   => 'items',
                        'collection' => [
                            'target' => MapperTestCollectionItemDto::class,
                            'source' => MapperTestCollectionItem::class,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            MapperTestCollectionItem::class . '->' . MapperTestCollectionItemDto::class,
            MapperTestCollectionParent::class . '->' . MapperTestCollectionParentDto::class,
        ], $objectMapper->warmup());

        $mapperTestCollectionParentDto = $objectMapper->map(
            new MapperTestCollectionParent([
                new MapperTestCollectionItem('first'),
                new MapperTestCollectionItem('second'),
            ]),
            MapperTestCollectionParentDto::class,
        );

        self::assertSame(['first', 'second'], array_map(static fn (MapperTestCollectionItemDto $mapperTestCollectionItemDto): string => $mapperTestCollectionItemDto->value, $mapperTestCollectionParentDto->items));
    }

    #[Test]
    public function itReportsCollectionRuntimeElementDiagnostics(): void
    {
        $objectMapper = $this->collectionObjectMapper();
        $objectMapper->warmup();

        $this->expectException(MappingExecutionFailed::class);
        $this->expectExceptionMessage('Could not execute collection mapping');
        $this->expectExceptionMessage('parameter "items"');
        $this->expectExceptionMessage(MapperTestCollectionItem::class);
        $this->expectExceptionMessage('got stdClass');

        $objectMapper->map(new MapperTestCollectionParent([new stdClass()]), MapperTestCollectionParentDto::class);
    }

    #[Test]
    public function itRejectsProductionCacheMissBeforeWarmup(): void
    {
        $objectMapper = $this->collectionObjectMapper();

        $this->expectException(MappingCompilationFailed::class);
        $this->expectExceptionMessage('Generated mapper cache miss');
        $this->expectExceptionMessage('Warm the cache before production use');

        $objectMapper->map(
            new MapperTestCollectionParent([new MapperTestCollectionItem('not-warmed')]),
            MapperTestCollectionParentDto::class,
        );
    }

    #[Test]
    public function itKeepsMappingsExactUnlessCycleProxyIsExplicit(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestProxySource::class . '->' . MapperTestProxyTarget::class => [
                'source' => MapperTestProxySource::class,
                'target' => MapperTestProxyTarget::class,
                'rules'  => [],
            ],
        ]);
        $objectMapper->warmup();

        self::assertSame('direct', $objectMapper->map(new MapperTestProxySource('direct'), MapperTestProxyTarget::class)->value);

        $this->expectException(MappingNotRegistered::class);
        $objectMapper->map(new MapperTestCycleProxySource('proxy'), MapperTestProxyTarget::class);
    }

    #[Test]
    public function itMapsOnlyTheExplicitCycleProxySourceMatchMode(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestProxySource::class . '->' . MapperTestProxyTarget::class => [
                'source'       => MapperTestProxySource::class,
                'target'       => MapperTestProxyTarget::class,
                'source_match' => 'cycle_proxy',
                'rules'        => [],
            ],
        ]);
        $objectMapper->warmup();

        self::assertSame('proxy', $objectMapper->map(new MapperTestCycleProxySource('proxy'), MapperTestProxyTarget::class)->value);
    }

    #[Test]
    public function itCompilesSourceLessTrustedConstants(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestConstantSource::class . '->' . MapperTestConstantTarget::class => [
                'source' => MapperTestConstantSource::class,
                'target' => MapperTestConstantTarget::class,
                'rules'  => [
                    'nullValue'   => [
                        'constant' => null,
                    ],
                    'boolValue'   => [
                        'constant' => true,
                    ],
                    'intValue'    => [
                        'constant' => 7,
                    ],
                    'floatValue'  => [
                        'constant' => 1.5,
                    ],
                    'stringValue' => [
                        'constant' => 'fixed',
                    ],
                ],
            ],
        ]);
        $objectMapper->warmup();

        self::assertEquals(new MapperTestConstantTarget(null, true, 7, 1.5, 'fixed'), $objectMapper->map(new MapperTestConstantSource(), MapperTestConstantTarget::class));
    }

    #[Test]
    public function itRejectsUnregisteredTransformerDuringWarmup(): void
    {
        $objectMapper = $this->objectMapper(
            [
                MapperTestChild::class . '->' . MapperTestChildDto::class => [
                    'source' => MapperTestChild::class,
                    'target' => MapperTestChildDto::class,
                    'rules'  => [
                        'id' => [
                            'property'    => 'id',
                            'transformer' => UuidInterfaceToStringTransformer::class,
                        ],
                    ],
                ],
            ],
            [],
            [
                UuidInterfaceToStringTransformer::class => new UuidInterfaceToStringTransformer(),
            ],
        );

        $this->expectException(MappingCompilationFailed::class);
        $this->expectExceptionMessage(UuidInterfaceToStringTransformer::class);

        $objectMapper->warmup();
    }

    #[Test]
    public function itRejectsWrongTransformerClassWithTheFullRulePath(): void
    {
        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('object_mapper.mappings.invalid.rules.value.transformer');

        $this->objectMapper([
            'invalid' => [
                'source' => MapperTestChild::class,
                'target' => MapperTestChildDto::class,
                'rules'  => [
                    'value' => [
                        'property'    => 'id',
                        'transformer' => stdClass::class,
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function itReportsMissingNestedPairDuringWarmup(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestNestedParent::class . '->' . MapperTestNestedParentDto::class => [
                'source' => MapperTestNestedParent::class,
                'target' => MapperTestNestedParentDto::class,
                'rules'  => [
                    'child' => [
                        'property' => 'child',
                        'nested'   => MapperTestChildDto::class,
                    ],
                ],
            ],
        ]);

        $this->expectException(MappingCompilationFailed::class);
        $this->expectExceptionMessage(MapperTestChild::class . ' -> ' . MapperTestChildDto::class);

        $objectMapper->warmup();
    }

    #[Test]
    public function itReportsNestedCyclesDuringWarmup(): void
    {
        $objectMapper = $this->objectMapper([
            MapperTestCycleA::class . '->' . MapperTestCycleADto::class => [
                'source' => MapperTestCycleA::class,
                'target' => MapperTestCycleADto::class,
                'rules'  => [
                    'b' => [
                        'property' => 'b',
                        'nested'   => MapperTestCycleBDto::class,
                    ],
                ],
            ],
            MapperTestCycleB::class . '->' . MapperTestCycleBDto::class => [
                'source' => MapperTestCycleB::class,
                'target' => MapperTestCycleBDto::class,
                'rules'  => [
                    'a' => [
                        'property' => 'a',
                        'nested'   => MapperTestCycleADto::class,
                    ],
                ],
            ],
        ]);

        $this->expectException(MappingCompilationFailed::class);
        $this->expectExceptionMessage('cycle');
        $this->expectExceptionMessage(MapperTestCycleA::class . '->' . MapperTestCycleADto::class);

        $objectMapper->warmup();
    }

    #[Test]
    public function itRejectsEveryInvalidGrammarCombinationWithTheFullRulePath(): void
    {
        $rulePath = 'object_mapper.mappings.invalid.rules.value';

        foreach ([
            'missing selector'                  => [
                'transformer' => UuidInterfaceToStringTransformer::class,
            ],
            'two selectors'                     => [
                'property' => 'id',
                'method'   => 'id',
            ],
            'two terminal operations'           => [
                'property'    => 'id',
                'transformer' => UuidInterfaceToStringTransformer::class,
                'nested'      => MapperTestChildDto::class,
            ],
            'constant with selector'            => [
                'property' => 'id',
                'constant' => 'fixed',
            ],
            'constant with terminal operation'  => [
                'constant'    => 'fixed',
                'transformer' => UuidInterfaceToStringTransformer::class,
            ],
            'extra rule key'                    => [
                'property'    => 'id',
                'unsupported' => true,
            ],
            'missing collection target'         => [
                'property'   => 'items',
                'collection' => [
                    'source' => MapperTestCollectionItem::class,
                ],
            ],
            'extra collection key'              => [
                'property'   => 'items',
                'collection' => [
                    'source' => MapperTestCollectionItem::class,
                    'target' => MapperTestCollectionItemDto::class,
                    'extra'  => true,
                ],
            ],
            'invalid collection element class'  => [
                'property'   => 'items',
                'collection' => [
                    'source' => 'not a class',
                    'target' => MapperTestCollectionItemDto::class,
                ],
            ],
            'invalid selector identifier'       => [
                'property' => 'not-a-property',
            ],
        ] as $rule) {
            try {
                $this->objectMapper([
                    'invalid' => [
                        'source' => MapperTestCollectionParent::class,
                        'target' => MapperTestCollectionParentDto::class,
                        'rules'  => [
                            'value' => $rule,
                        ],
                    ],
                ]);
                self::fail('Expected invalid rule configuration to be rejected.');
            } catch (InvalidConfigValueException $exception) {
                self::assertStringContainsString($rulePath, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function itRejectsInvalidSourceMatchAndNonFiniteConstants(): void
    {
        foreach ([
            'invalid source match' => [
                'source_match' => 'subclass',
                'rules'        => [],
            ],
            'non-finite constant'  => [
                'rules' => [
                    'value' => [
                        'constant' => INF,
                    ],
                ],
            ],
        ] as $name => $configuration) {
            try {
                $this->objectMapper([
                    'invalid' => [
                        'source' => MapperTestConstantSource::class,
                        'target' => MapperTestConstantTarget::class,
                        ...$configuration,
                    ],
                ]);
                self::fail(sprintf('Expected %s configuration to be rejected.', $name));
            } catch (InvalidConfigValueException $exception) {
                self::assertStringContainsString('object_mapper.mappings.invalid', $exception->getMessage());
            }
        }
    }

    #[Test]
    public function itRejectsRootLikeAndPermissiveCacheBases(): void
    {
        $permissiveDirectory = sys_get_temp_dir() . '/packio-object-mapper-permissive-' . bin2hex(random_bytes(8));
        mkdir($permissiveDirectory, 0o700);
        chmod($permissiveDirectory, 0o777);

        try {
            foreach (['/', $permissiveDirectory] as $cacheDirectory) {
                try {
                    $this->objectMapperForCacheDirectory($cacheDirectory, []);
                    self::fail('Expected unsafe mapper cache base to be rejected.');
                } catch (InvalidConfigValueException|MappingCompilationFailed $exception) {
                    self::assertStringContainsString('cache', $exception->getMessage());
                }
            }
        } finally {
            chmod($permissiveDirectory, 0o700);
            rmdir($permissiveDirectory);
        }
    }

    #[Test]
    public function itRejectsSymbolicLinkCacheBase(): void
    {
        $targetDirectory = sys_get_temp_dir() . '/packio-object-mapper-target-' . bin2hex(random_bytes(8));
        $linkDirectory   = sys_get_temp_dir() . '/packio-object-mapper-link-' . bin2hex(random_bytes(8));
        mkdir($targetDirectory, 0o700);
        symlink($targetDirectory, $linkDirectory);

        try {
            $this->objectMapperForCacheDirectory($linkDirectory, []);
            self::fail('Expected symbolic-link mapper cache base to be rejected.');
        } catch (MappingCompilationFailed $exception) {
            self::assertStringContainsString('symbolic link', $exception->getMessage());
        } finally {
            unlink($linkDirectory);
            rmdir($targetDirectory);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $mappings
     * @param list<class-string>                  $transformers
     * @param array<class-string, object>         $services
     */
    private function objectMapper(array $mappings, array $transformers = [], array $services = []): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-object-mapper-' . bin2hex(random_bytes(8));

        return $this->objectMapperForCacheDirectory($this->cacheDirectory, $mappings, $transformers, $services);
    }

    /**
     * @param array<string, array<string, mixed>> $mappings
     * @param list<class-string>                  $transformers
     * @param array<class-string, object>         $services
     */
    private function objectMapperForCacheDirectory(
        string $cacheDirectory,
        array $mappings,
        array $transformers = [],
        array $services = [],
    ): ObjectMapper {
        return (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config' => [
                    'object_mapper' => [
                        'cache_dir'          => $cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => $transformers,
                        'mappings'           => $mappings,
                    ],
                ],
                ...$services,
            ],
        ]));
    }

    private function collectionObjectMapper(): ObjectMapper
    {
        return $this->objectMapper([
            MapperTestCollectionItem::class . '->' . MapperTestCollectionItemDto::class     => [
                'source' => MapperTestCollectionItem::class,
                'target' => MapperTestCollectionItemDto::class,
                'rules'  => [],
            ],
            MapperTestCollectionParent::class . '->' . MapperTestCollectionParentDto::class => [
                'source' => MapperTestCollectionParent::class,
                'target' => MapperTestCollectionParentDto::class,
                'rules'  => [
                    'items' => [
                        'property'   => 'items',
                        'collection' => [
                            'source' => MapperTestCollectionItem::class,
                            'target' => MapperTestCollectionItemDto::class,
                        ],
                    ],
                ],
            ],
        ]);
    }
}

final readonly class MapperTestChild
{
    public function __construct(public UuidInterface $id) {}
}

final readonly class MapperTestChildDto
{
    public function __construct(public string $id) {}
}

final readonly class MapperTestParent
{
    public function __construct(public MapperTestChild $child, private Chronos $at) {}

    public function at(): Chronos
    {
        return $this->at;
    }
}

final readonly class MapperTestParentDto
{
    public function __construct(public MapperTestChildDto $child, public string $at) {}
}

final readonly class MapperTestCollectionItem
{
    public function __construct(public string $value) {}
}

final readonly class MapperTestCollectionItemDto
{
    public function __construct(public string $value) {}
}

final readonly class MapperTestCollectionParent
{
    /** @param list<object> $items */
    public function __construct(public array $items) {}
}

final readonly class MapperTestCollectionParentDto
{
    /** @param list<MapperTestCollectionItemDto> $items */
    public function __construct(public array $items) {}
}

final readonly class MapperTestNestedParent
{
    public function __construct(public MapperTestChild $child) {}
}

final readonly class MapperTestNestedParentDto
{
    public function __construct(public MapperTestChildDto $child) {}
}

final readonly class MapperTestCycleA
{
    public function __construct(public MapperTestCycleB $b) {}
}

final readonly class MapperTestCycleADto
{
    public function __construct(public MapperTestCycleBDto $b) {}
}

final readonly class MapperTestCycleB
{
    public function __construct(public MapperTestCycleA $a) {}
}

final readonly class MapperTestCycleBDto
{
    public function __construct(public MapperTestCycleADto $a) {}
}

class MapperTestProxySource
{
    public function __construct(public string $value) {}
}

final class MapperTestCycleProxySource extends MapperTestProxySource implements EntityProxyInterface {}

final readonly class MapperTestProxyTarget
{
    public function __construct(public string $value) {}
}

final class MapperTestConstantSource {}

final readonly class MapperTestConstantTarget
{
    public function __construct(
        public ?string $nullValue,
        public bool $boolValue,
        public int $intValue,
        public float $floatValue,
        public string $stringValue,
    ) {}
}
