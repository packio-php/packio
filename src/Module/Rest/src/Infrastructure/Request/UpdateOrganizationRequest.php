<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class UpdateOrganizationRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function name(string $default): string
    {
        return ApiRequestPayload::string($this->payload, 'name', $default);
    }

    public function anonymousAccess(bool $default): ?bool
    {
        return ApiRequestPayload::boolean($this->payload, 'anonymous_access', $default, 'anonymousAccess');
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        if (ApiRequestPayload::has($this->payload, 'name') && '' === $this->name('')) {
            return [
                'name' => [new Required(message: 'Name is required.')],
            ];
        }

        return [
            'anonymous_access' => [new In([true, false], strict: true, message: 'Anonymous access must be a boolean.')],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'name'             => $this->name(''),
            'anonymous_access' => $this->anonymousAccess(false),
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
