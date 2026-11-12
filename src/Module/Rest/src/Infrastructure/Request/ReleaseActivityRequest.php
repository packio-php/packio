<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use CuyZ\Valinor\Mapper\Http\FromQuery;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class ReleaseActivityRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $query
     */
    public function __construct(
        #[FromBody(asRoot: true)]
        public array $payload = [],
        #[FromQuery(asRoot: true)]
        public array $query = [],
    ) {}

    public function version(): string
    {
        return ApiRequestPayload::string($this->query, 'version');
    }

    public function active(): ?bool
    {
        return ApiRequestPayload::boolean($this->payload, 'active', null);
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        if ('' === $this->version()) {
            return [
                'version' => [new Required(message: 'Version is required.')],
            ];
        }

        return [
            'active' => [new In([true, false], strict: true, message: 'Active must be a boolean.')],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'version' => $this->version(),
            'active'  => $this->active(),
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
