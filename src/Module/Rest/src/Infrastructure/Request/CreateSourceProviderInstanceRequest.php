<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;
use function in_array;

final readonly class CreateSourceProviderInstanceRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function toDto(): ?SourceProviderInstanceRequestDto
    {
        $providerType = ApiRequestPayload::string($this->payload, 'provider_type', null, 'providerType');
        $label        = ApiRequestPayload::string($this->payload, 'label');
        $baseUrl      = ApiRequestPayload::string($this->payload, 'base_url', null, 'baseUrl');
        $authMode     = ApiRequestPayload::string($this->payload, 'auth_mode', null, 'authMode');
        if (in_array('', [$providerType, $label, $baseUrl, $authMode], true)) {
            return null;
        }

        return new SourceProviderInstanceRequestDto(
            providerType: $providerType,
            label: $label,
            baseUrl: $baseUrl,
            clientId: ApiRequestPayload::string($this->payload, 'client_id', null, 'clientId'),
            clientSecret: ApiRequestPayload::optionalString($this->payload, 'client_secret', 'clientSecret'),
            authMode: $authMode,
            metadata: ApiRequestPayload::object($this->payload, 'metadata') ?? [],
        );
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        return [
            'source_provider_instance' => [new Required(message: 'Provider type, label, base_url and auth_mode are required.')],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'source_provider_instance' => $this->toDto(),
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
