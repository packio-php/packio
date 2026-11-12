<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\EmptyCondition\WhenMissing;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\RulesProvider\AttributesRulesProvider;
use Yiisoft\Validator\RulesProviderInterface;

use function array_intersect_key;
use function array_key_exists;
use function filter_var;
use function is_bool;
use function is_int;
use function is_string;
use function trim;

/** Shared request fields; normalization preserves presence for attribute validation. */
abstract readonly class SourceSettingsInput implements DataSetInterface, RulesProviderInterface
{
    #[In(['public', 'private'], strict: true, message: 'Visibility must be public or private.', skipOnEmpty: new WhenMissing())]
    public ?string $visibility;

    #[In([true, false], strict: true, message: 'Active must be a boolean.', skipOnEmpty: new WhenMissing())]
    public ?bool $active;

    #[Integer(
        min: 0,
        incorrectInputMessage: 'Keep last releases must be a non-negative integer.',
        notNumberMessage: 'Keep last releases must be a non-negative integer.',
        lessThanMinMessage: 'Keep last releases must be a non-negative integer.',
        skipOnEmpty: new WhenMissing(),
    )]
    public ?int $keepLastReleases;

    /** @var array<string, null|bool|int|string> */
    private array $data;

    /** @param array<array-key, mixed> $payload */
    public function __construct(array $payload = [])
    {
        $this->visibility = is_string($payload['visibility'] ?? null) ? trim($payload['visibility']) : null;
        $active           = $payload['active'] ?? null;
        $this->active     = is_bool($active)
            ? $active
            : ((is_string($active) || is_int($active)) ? filter_var($active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null);

        if (array_key_exists('keep_last_releases', $payload)) {
            $payload['keepLastReleases'] = $payload['keep_last_releases'];
        }
        $retention              = $payload['keepLastReleases'] ?? null;
        $this->keepLastReleases = is_int($retention)
            ? $retention
            : ((is_string($retention) && '' !== trim($retention) && (string) (int) $retention === trim($retention)) ? (int) $retention : null);
        $this->data = array_intersect_key([
            'visibility'       => $this->visibility,
            'active'           => $this->active,
            'keepLastReleases' => $this->keepLastReleases,
        ], $payload);
    }

    public function getRules(): iterable
    {
        return new AttributesRulesProvider($this)->getRules();
    }

    /** @return array<string, null|bool|int|string> */
    public function getData(): array
    {
        return $this->data;
    }

    public function getPropertyValue(string $property): mixed
    {
        return $this->data[$property] ?? null;
    }

    public function hasProperty(string $property): bool
    {
        return array_key_exists($property, $this->data);
    }

    public function toPatch(): SourceSettingsPatch
    {
        return new SourceSettingsPatch($this->visibility, $this->active, $this->keepLastReleases);
    }
}
