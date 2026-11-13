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
use function filter_var;
use function in_array;
use function trim;

final readonly class UpdateAdminUserRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function email(string $default): string
    {
        return ApiRequestPayload::string($this->payload, 'email', $default);
    }

    public function displayName(string $default): string
    {
        return ApiRequestPayload::string($this->payload, 'display_name', $default, 'displayName');
    }

    /**
     * @param list<string> $default
     *
     * @return list<string>
     */
    public function roles(array $default): array
    {
        return $this->normalizeRoles(ApiRequestPayload::stringList($this->payload, 'roles', $default));
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        if (! ApiRequestPayload::isStringListOrAbsent($this->payload, 'roles')) {
            return [
                'roles' => [new Callback(static fn (): Result => new Result()->addError('Roles must be a list of strings.'))],
            ];
        }

        if (ApiRequestPayload::has($this->payload, 'roles')) {
            foreach (ApiRequestPayload::stringList($this->payload, 'roles', []) as $role) {
                $role = trim($role);
                if (! in_array($role, ['user', 'admin'], true)) {
                    return [
                        'roles' => [new Callback(static fn (): Result => new Result()->addError('Unknown role: ' . $role . '.'))],
                    ];
                }
            }
        }

        if (ApiRequestPayload::has($this->payload, 'email') && false === filter_var($this->email(''), FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => [new Callback(static fn (): Result => new Result()->addError('Email is invalid.'))],
            ];
        }

        if (ApiRequestPayload::has($this->payload, 'display_name', 'displayName')) {
            return [
                'display_name' => [new Required(message: 'Display name is required.')],
            ];
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'email'        => $this->email(''),
            'display_name' => $this->displayName(''),
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

    /**
     * @param list<string> $value
     *
     * @return list<string>
     */
    private function normalizeRoles(array $value): array
    {
        $roles = [];
        foreach ($value as $role) {
            $role = trim($role);
            if (in_array($role, ['user', 'admin'], true)) {
                $roles[] = $role;
            }
        }

        return [] !== $roles ? $roles : ['user'];
    }
}
