<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;

use function array_first;
use function array_key_exists;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Resolves the ordered validation plan for the matched route.
 *
 * The plan reads only explicit `mapped_request_validations` metadata produced by
 * `#[ValidateMappedRequest]` and links every entry with its `valinor_mappings`
 * DTO. The `validation_failed` policy metadata is only consulted as an optional
 * response override; it never enables or disables validation.
 */
final class MappedRequestValidationPlanResolver
{
    public const string DEFAULT_FAILURE_OPTIONS = 'packio_canonical_default';

    /**
     * @return list<ValidationTargetPlanItem>
     *
     * @throws InvalidMappedRequestValidationPlan when the static route metadata is inconsistent
     */
    public function resolve(ServerRequestInterface $serverRequest): array
    {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return [];
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        if (false === $matchedRoute) {
            return [];
        }

        return $this->resolveFromOptions($matchedRoute->getOptions());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<ValidationTargetPlanItem>
     */
    public function resolveFromOptions(array $options): array
    {
        if (! array_key_exists('mapped_request_validations', $options)) {
            return [];
        }

        $entries = $options['mapped_request_validations'];

        if (! is_array($entries)) {
            throw new InvalidMappedRequestValidationPlan('mapped_request_validations metadata must be a list of entries.');
        }

        if ([] === $entries) {
            return [];
        }

        $mappings = $options['valinor_mappings'] ?? null;
        if (! is_array($mappings) || [] === $mappings) {
            throw new InvalidMappedRequestValidationPlan('Validation metadata without valinor_mappings is invalid.');
        }

        $policy = $this->policy(array_key_exists('validation_failed', $options) ? $options['validation_failed'] : []);

        $mappedDtos = [];
        foreach ($mappings as $mapping) {
            $mappedDtos[] = self::mappingDto($mapping);
        }

        $plan = [];
        $seen = [];

        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry)) {
                throw new InvalidMappedRequestValidationPlan(
                    sprintf('mapped_request_validations entry at index %d must be an array.', $index),
                );
            }

            $dto = $entry['dto'] ?? null;
            if (! is_string($dto) || '' === $dto) {
                throw new InvalidMappedRequestValidationPlan(
                    sprintf('mapped_request_validations entry at index %d must declare a dto class-string.', $index),
                );
            }

            if (! in_array($dto, $mappedDtos, true)) {
                throw new InvalidMappedRequestValidationPlan(
                    sprintf('Validation target %s has no valinor_mappings entry with the same DTO.', $dto),
                );
            }

            if (isset($seen[$dto])) {
                throw new InvalidMappedRequestValidationPlan(
                    sprintf('Duplicate validation target %s on a single route.', $dto),
                );
            }
            $seen[$dto] = true;

            $plan[] = new ValidationTargetPlanItem(targetAttribute: $dto, options: $policy);
        }

        return $plan;
    }

    /**
     * The DTO class a `valinor_mappings` entry maps; null for a malformed entry.
     */
    public static function mappingDto(mixed $mapping): ?string
    {
        if (! is_array($mapping)) {
            return null;
        }

        foreach (['body', 'query', 'route', 'source'] as $sourceKey) {
            $value = $mapping[$sourceKey] ?? null;
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidMappedRequestValidationPlan
     */
    private function policy(mixed $policies): array
    {
        if (! is_array($policies)) {
            throw new InvalidMappedRequestValidationPlan('validation_failed metadata must be a list of policies.');
        }

        foreach (array_values($policies) as $index => $policy) {
            if (! is_array($policy)) {
                throw new InvalidMappedRequestValidationPlan(
                    sprintf('validation_failed policy at index %d must be an array.', $index),
                );
            }
        }

        if (count($policies) > 1) {
            throw new InvalidMappedRequestValidationPlan(
                'Multiple validation-failed policies on a single route are ambiguous.',
            );
        }

        $policy = array_first($policies);

        return is_array($policy) ? $policy : [
            self::DEFAULT_FAILURE_OPTIONS => true,
        ];
    }
}
