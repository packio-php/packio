<?php

declare(strict_types=1);

namespace Packio\Application\Routing\Attribute;

use Attribute;
use InvalidArgumentException;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;

use function trim;

/**
 * Explicit per-route switch for Yiisoft semantic validation of a mapped DTO.
 *
 * The attribute owns only validation metadata. Response customization on failure
 * stays with the repeatable `#[ValidationFailed]` attribute; its presence is never
 * required to run validation.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ValidateMappedRequest implements AggregatingRouteAttributeModifierInterface
{
    public const string MIDDLEWARE_KEY = 'packio.application.mapped-request-validator';

    /**
     * @param string $dto mapped DTO class as declared by the matching #[MapRequest]
     */
    public function __construct(
        public string $dto,
    ) {
        if ('' === trim($this->dto) || trim($this->dto) !== $this->dto) {
            throw new InvalidArgumentException(
                'ValidateMappedRequest: $dto must be a non-empty string without surrounding whitespace.',
            );
        }
    }

    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function mergeDefaults(array $defaults): array
    {
        $entries   = $defaults['mapped_request_validations'] ?? [];
        $entries[] = [
            'dto' => $this->dto,
        ];

        return [
            ...$defaults,
            'mapped_request_validations' => $entries,
        ];
    }

    public function getUniqueMiddleware(): array
    {
        return [
            self::MIDDLEWARE_KEY => ValidateMappedRequestMiddleware::class,
        ];
    }
}
