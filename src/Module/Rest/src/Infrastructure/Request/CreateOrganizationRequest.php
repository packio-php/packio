<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

final readonly class CreateOrganizationRequest implements RulesProviderInterface, DataSetInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    /**
     * @return array<string, list<RuleInterface>>
     */
    public function getRules(): iterable
    {
        return [
            'name' => [
                new Required(message: 'Name is required.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'name' => $this->name(),
        ];
    }

    public function getPropertyValue(string $property): mixed
    {
        return 'name' === $property ? $this->name() : null;
    }

    public function hasProperty(string $property): bool
    {
        return 'name' === $property;
    }

    public function name(): string
    {
        return ApiRequestPayload::string($this->payload, 'name');
    }
}
