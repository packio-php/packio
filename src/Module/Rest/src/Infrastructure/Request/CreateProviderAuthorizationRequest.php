<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class CreateProviderAuthorizationRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function provider(): string
    {
        return ApiRequestPayload::string($this->payload, 'provider');
    }

    public function authStrategy(): string
    {
        return ApiRequestPayload::string($this->payload, 'auth_strategy', 'token', 'authStrategy') ?: 'token';
    }

    /** @return null|array<string, mixed> */
    public function authorizationPayload(): ?array
    {
        return ApiRequestPayload::object($this->payload, 'payload');
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        if ('' === $this->provider()) {
            return [
                'provider' => [new Required(message: 'Provider is required.')],
            ];
        }

        return [
            'payload' => [new Callback(static fn (mixed $value): Result => null === $value
                        ? new Result()->addError('Payload must be an object.') : new Result())],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'provider' => $this->provider(),
            'payload'  => $this->authorizationPayload(),
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
