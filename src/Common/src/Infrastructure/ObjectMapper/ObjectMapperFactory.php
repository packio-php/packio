<?php

declare(strict_types=1);

namespace Common\Infrastructure\ObjectMapper;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\ContainerResolverException;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ObjectMapper\Contract\ValueTransformerInterface;
use Sirix\ObjectMapper\Definition\MappingDefinition;
use Sirix\ObjectMapper\Definition\MapRule;
use Sirix\ObjectMapper\Definition\SourceMatchMode;
use Sirix\ObjectMapper\Exception\MappingCompilationFailed;
use Sirix\ObjectMapper\Generator\MapperCache;
use Sirix\ObjectMapper\Generator\PhpMapperGenerator;
use Sirix\ObjectMapper\Metadata\MappingMetadataFactory;
use Sirix\ObjectMapper\Runtime\MappingRegistry;
use Sirix\ObjectMapper\Runtime\ObjectMapper;
use Sirix\ObjectMapper\Runtime\ValueTransformerRegistry;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function class_exists;
use function count;
use function fileperms;
use function is_a;
use function is_array;
use function is_bool;
use function is_dir;
use function is_finite;
use function is_float;
use function is_int;
use function is_link;
use function is_string;
use function mkdir;
use function rtrim;
use function sprintf;

/**
 * Application-owned grammar for trusted conventional object projections.
 *
 * Selector rules have exactly one `property`, `getter`, or `method` and may
 * have one terminal operation: `transformer`, `nested`, or `collection`.
 * A `constant` is source-less and cannot be combined with a selector or
 * terminal operation. Structural rules require a registered pair in this
 * same registry. Source matching is exact unless `cycle_proxy` is explicit.
 */
final class ObjectMapperFactory
{
    public function __invoke(ContainerInterface $container): ObjectMapper
    {
        $containerResolver        = ContainerResolver::forFactory($container, self::class);
        $configReader             = ConfigReader::fromContainer($containerResolver);
        $valueTransformerRegistry = new ValueTransformerRegistry($this->transformers($configReader, $containerResolver));
        $mappingRegistry          = new MappingRegistry($this->definitions($configReader, $containerResolver));
        $cacheDirectory           = $this->cacheDirectory($configReader);

        return new ObjectMapper(
            $mappingRegistry,
            new MapperCache(
                new MappingMetadataFactory($valueTransformerRegistry, mappingRegistry: $mappingRegistry),
                new PhpMapperGenerator(),
                $cacheDirectory,
                $valueTransformerRegistry,
                $configReader->bool('object_mapper.generate_on_demand', false),
                $mappingRegistry,
                reusePreparedMappings: $configReader->bool('object_mapper.reuse_prepared_mappings', false),
            ),
        );
    }

    private function cacheDirectory(ConfigReader $configReader): string
    {
        $configuredDirectory = $configReader->requiredNonEmptyString('object_mapper.cache_dir');
        $baseDirectory       = rtrim($configuredDirectory, '/');
        if ('' === $baseDirectory) {
            throw InvalidConfigValueException::forType(
                'object_mapper.cache_dir',
                'a non-root owner-only cache directory',
                $configuredDirectory,
                self::class,
            );
        }

        if (is_link($baseDirectory)) {
            throw new MappingCompilationFailed(sprintf('Mapper cache base directory %s must not be a symbolic link.', $baseDirectory));
        }

        if (! is_dir($baseDirectory) && ! mkdir($concurrentDirectory = $baseDirectory, 0o700, true) && ! is_dir($concurrentDirectory)) {
            throw new MappingCompilationFailed(sprintf('Could not create mapper cache base directory %s.', $baseDirectory));
        }

        $mode = fileperms($baseDirectory);
        if (false === $mode || 0 !== ($mode & 0o077)) {
            throw new MappingCompilationFailed(sprintf(
                'Mapper cache base directory %s must be accessible only by its owner (0700).',
                $baseDirectory,
            ));
        }

        return $baseDirectory . '/v8';
    }

    /** @return iterable<MappingDefinition> */
    private function definitions(ConfigReader $configReader, ContainerResolver $containerResolver): iterable
    {
        foreach ($configReader->map('object_mapper.mappings', []) as $key => $mapping) {
            $mappingPath = sprintf('object_mapper.mappings.%s', $key);
            if (! is_array($mapping)) {
                throw InvalidConfigValueException::forType($mappingPath, 'map', $mapping, self::class);
            }

            $this->assertMappingKeys($mapping, $mappingPath);
            $mappingConfigReader = ConfigReader::fromArray($mapping, self::class);
            $source              = $mappingConfigReader->requiredNonEmptyString('source');
            $target              = $mappingConfigReader->requiredNonEmptyString('target');
            if (! class_exists($source) || ! class_exists($target)) {
                throw InvalidConfigValueException::forType(
                    $mappingPath,
                    'existing source and target class names',
                    null,
                    self::class,
                );
            }

            yield new MappingDefinition(
                $source,
                $target,
                $this->rules($mappingConfigReader, $mappingPath, $containerResolver),
                $mappingConfigReader->nonEmptyStringList('ignored_source', []),
                $this->sourceMatch($mappingConfigReader, $mappingPath),
            );
        }
    }

    /**
     * @return array<string, MapRule>
     */
    private function rules(ConfigReader $mappingConfigReader, string $mappingPath, ContainerResolver $containerResolver): array
    {
        $rules = [];
        foreach ($mappingConfigReader->map('rules', []) as $targetParameter => $rule) {
            $rulePath = sprintf('%s.rules.%s', $mappingPath, $targetParameter);
            if (! is_array($rule)) {
                throw InvalidConfigValueException::forType($rulePath, 'map', $rule, self::class);
            }

            $this->assertRuleKeys($rule, $rulePath);
            $ruleConfigReader = ConfigReader::fromArray($rule, self::class);
            $selectors        = array_values(array_filter(['property', 'getter', 'method'], $ruleConfigReader->has(...)));
            $operations       = array_values(array_filter(['transformer', 'nested', 'collection'], $ruleConfigReader->has(...)));
            $hasConstant      = array_key_exists('constant', $rule);
            if ($hasConstant) {
                if ([] !== $selectors || [] !== $operations) {
                    throw InvalidConfigValueException::forType($rulePath, 'a source-less constant without selectors or terminal operations', $rule, self::class);
                }

                $rules[$targetParameter] = MapRule::constant($this->constant($rule['constant'], $rulePath));

                continue;
            }

            if (1 !== count($selectors) || 1 < count($operations)) {
                throw InvalidConfigValueException::forType($rulePath, 'exactly one selector and at most one terminal operation', $rule, self::class);
            }

            try {
                $mapRule = match ($selectors[0]) {
                    'property' => MapRule::from($ruleConfigReader->requiredNonEmptyString('property')),
                    'getter'   => MapRule::fromGetter($ruleConfigReader->requiredNonEmptyString('getter')),
                    'method'   => MapRule::fromMethod($ruleConfigReader->requiredNonEmptyString('method')),
                };

                if ([] === $operations) {
                    $rules[$targetParameter] = $mapRule;

                    continue;
                }

                $rules[$targetParameter] = match ($operations[0]) {
                    'transformer' => $mapRule->through(
                        $this->transformer($ruleConfigReader->requiredNonEmptyString('transformer'), $containerResolver, $rulePath),
                    ),
                    'nested'      => $mapRule->nested($ruleConfigReader->requiredNonEmptyString('nested')),
                    'collection'  => $this->collection($mapRule, $ruleConfigReader->required('collection'), $rulePath),
                };
            } catch (ContainerResolverException|InvalidArgumentException|InvalidConfigValueException $exception) {
                throw new InvalidConfigValueException(sprintf('Invalid object mapper rule at "%s": %s', $rulePath, $exception->getMessage()), $exception->getCode(), previous: $exception);
            }
        }

        return $rules;
    }

    /** @return iterable<ValueTransformerInterface> */
    private function transformers(ConfigReader $configReader, ContainerResolver $containerResolver): iterable
    {
        foreach ($configReader->nonEmptyStringList('object_mapper.transformers', []) as $transformer) {
            yield $containerResolver->getAs($transformer, ValueTransformerInterface::class);
        }
    }

    /** @return class-string<ValueTransformerInterface> */
    private function transformer(string $transformer, ContainerResolver $containerResolver, string $rulePath): string
    {
        if (! is_a($transformer, ValueTransformerInterface::class, true)) {
            throw InvalidConfigValueException::forType(
                $rulePath . '.transformer',
                ValueTransformerInterface::class . ' class string',
                $transformer,
                self::class,
            );
        }

        $containerResolver->getAs($transformer, ValueTransformerInterface::class);

        return $transformer;
    }

    private function collection(MapRule $mapRule, mixed $collection, string $rulePath): MapRule
    {
        if (! is_array($collection)
            || 2 !== count($collection)
            || [] !== array_diff(['source', 'target'], array_keys($collection))) {
            throw InvalidConfigValueException::forType($rulePath . '.collection', 'a map with source and target class names', $collection, self::class);
        }

        $collectionConfigReader = ConfigReader::fromArray($collection, self::class);

        return $mapRule->collection(
            $collectionConfigReader->requiredNonEmptyString('source'),
            $collectionConfigReader->requiredNonEmptyString('target'),
        );
    }

    private function sourceMatch(ConfigReader $mappingConfigReader, string $mappingPath): SourceMatchMode
    {
        if (! $mappingConfigReader->has('source_match')) {
            return SourceMatchMode::Exact;
        }

        return match ($mappingConfigReader->requiredNonEmptyString('source_match')) {
            'cycle_proxy' => SourceMatchMode::CycleProxy,
            default       => throw InvalidConfigValueException::forType(
                $mappingPath . '.source_match',
                'the explicit "cycle_proxy" source match mode',
                $mappingConfigReader->required('source_match'),
                self::class,
            ),
        };
    }

    private function constant(mixed $constant, string $rulePath): bool|float|int|string|null
    {
        if (null === $constant || is_bool($constant) || is_int($constant) || is_string($constant) || (is_float($constant) && is_finite($constant))) {
            return $constant;
        }

        throw InvalidConfigValueException::forType($rulePath . '.constant', 'null, bool, int, finite float, or string', $constant, self::class);
    }

    /** @param array<string, mixed> $mapping */
    private function assertMappingKeys(array $mapping, string $mappingPath): void
    {
        $extraKeys = array_diff(array_keys($mapping), ['source', 'target', 'rules', 'ignored_source', 'source_match']);
        if ([] !== $extraKeys) {
            throw InvalidConfigValueException::forType($mappingPath, 'only source, target, rules, ignored_source, and source_match keys', $mapping, self::class);
        }
    }

    /** @param array<string, mixed> $rule */
    private function assertRuleKeys(array $rule, string $rulePath): void
    {
        $extraKeys = array_diff(array_keys($rule), ['property', 'getter', 'method', 'transformer', 'nested', 'collection', 'constant']);
        if ([] !== $extraKeys) {
            throw InvalidConfigValueException::forType($rulePath, 'only selector and terminal operation keys', $rule, self::class);
        }
    }
}
