<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class CreateApiAccessTokenRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function name(): string
    {
        return ApiRequestPayload::string($this->payload, 'name');
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return ApiRequestPayload::stringList($this->payload, 'scopes', ['read']);
    }

    public function expiresAt(): ?string
    {
        return ApiRequestPayload::optionalString($this->payload, 'expires_at', 'expiresAt');
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        return [
            'name' => [new Required(message: 'Name is required.')],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'name' => $this->name(),
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
