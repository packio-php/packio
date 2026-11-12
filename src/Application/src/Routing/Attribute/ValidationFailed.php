<?php

declare(strict_types=1);

namespace Packio\Application\Routing\Attribute;

use Attribute;
use InvalidArgumentException;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_string;
use function trim;

/**
 * Repeatable response-policy override for semantic validation failures.
 *
 * The attribute never registers middleware and never enables validation; it only
 * customizes the failure response for a matching validation target.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ValidationFailed implements AggregatingRouteAttributeModifierInterface
{
    /**
     * @var list<string>
     */
    public array $oldInput;

    /**
     * @param null|non-empty-string $redirectTo
     * @param non-empty-string      $message
     * @param non-empty-string      $level
     * @param mixed[]               $oldInput
     */
    public function __construct(
        public ?string $redirectTo = null,
        public string $message = 'Invalid request data.',
        public ValidationNotify $notify = ValidationNotify::Flash,
        public string $level = 'error',
        public int $jsonStatus = 422,
        array $oldInput = [],
    ) {
        if ($jsonStatus < 400 || $jsonStatus > 599) {
            throw new InvalidArgumentException('ValidationFailed: $jsonStatus must be between 400 and 599.');
        }

        $this->oldInput = array_filter($oldInput, static fn (mixed $field): bool => is_string($field) && '' !== trim($field))
                |> (fn ($x) => array_map(trim(...), $x))
                |> array_unique(...)
                |> array_values(...);
    }

    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function getUniqueMiddleware(): array
    {
        return [];
    }

    public function mergeDefaults(array $defaults): array
    {
        $entries   = $defaults['validation_failed'] ?? [];
        $entries[] = [
            'redirect_to' => $this->redirectTo,
            'message'     => $this->message,
            'notify'      => $this->notify->value,
            'level'       => $this->level,
            'json_status' => $this->jsonStatus,
            'old_input'   => $this->oldInput,
        ];

        return [
            ...$defaults,
            'validation_failed' => $entries,
        ];
    }
}
