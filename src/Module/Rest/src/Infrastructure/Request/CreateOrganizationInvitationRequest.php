<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class CreateOrganizationInvitationRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function role(): string
    {
        return ApiRequestPayload::string($this->payload, 'role');
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        return [
            'role' => [new In(['member', 'owner'], strict: true, message: 'Role must be member or owner.')],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'role' => $this->role(),
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
