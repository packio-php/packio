<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;
use function is_array;

final readonly class UpdateAdminSettingsRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    /** @return null|array<string, mixed> */
    public function settings(): ?array
    {
        $settings = array_key_exists('settings', $this->payload) ? $this->payload['settings'] : $this->payload;

        return is_array($settings) ? $settings : null;
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        return [
            'settings' => [new Callback(static fn (mixed $value): Result => null === $value
                        ? new Result()->addError('Settings must be an object.') : new Result())],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'settings' => $this->settings(),
        ];
    }

    public function getPropertyValue(string $property): mixed
    {
        return $this->getData()[$property] ?? null;
    }

    public function hasProperty(string $property): bool
    {
        return array_key_exists($property, $this->getData());
    }
}
