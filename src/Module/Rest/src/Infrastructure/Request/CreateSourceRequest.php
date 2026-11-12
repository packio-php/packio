<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Request;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\RulesProviderInterface;

use function array_key_exists;

final readonly class CreateSourceRequest implements DataSetInterface, RulesProviderInterface
{
    /**
     * @param array<array-key, null|array<array-key, null|array<array-key, null|scalar>|scalar>|scalar> $payload
     */
    public function __construct(#[FromBody(asRoot: true)] public array $payload = []) {}

    public function provider(): string
    {
        return ApiRequestPayload::string($this->payload, 'provider');
    }

    public function resource(): string
    {
        return ApiRequestPayload::string($this->payload, 'resource');
    }

    public function defaultRef(): string
    {
        return ApiRequestPayload::string($this->payload, 'default_ref', 'main', 'defaultRef') ?: 'main';
    }

    public function visibility(): ?string
    {
        $visibility = ApiRequestPayload::string($this->payload, 'visibility', 'public');

        return 'public' === $visibility || 'private' === $visibility ? $visibility : null;
    }

    public function providerAuthorizationId(): string
    {
        return ApiRequestPayload::string($this->payload, 'provider_authorization_id', null, 'providerAuthorizationId');
    }

    /** @return null|array<string, mixed> */
    public function settings(): ?array
    {
        return ApiRequestPayload::object($this->payload, 'settings');
    }

    public function keepLastReleases(): ?int
    {
        return ApiRequestPayload::nonNegativeInteger($this->payload, 'keep_last_releases', 0, 'keepLastReleases');
    }

    /** @return array<string, list<RuleInterface>> */
    public function getRules(): iterable
    {
        // Keep the original first-failure group and public messages.
        if ('' === $this->provider() || '' === $this->resource()) {
            return [
                'provider' => [new Required(message: 'Provider is required.')],
                'resource' => [new Required(message: 'Resource is required.')],
            ];
        }

        if (null === $this->visibility()) {
            return [
                'visibility' => [new In(['public', 'private'], strict: true, message: 'Visibility must be public or private.')],
            ];
        }

        if (null === $this->keepLastReleases()) {
            return [
                'keep_last_releases' => [new Required(message: 'Keep last releases must be a non-negative integer.')],
            ];
        }

        return [
            'settings' => [new Callback(static fn (mixed $value): Result => null === $value
                        ? new Result()->addError('Settings must be an object.') : new Result())],
        ];
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return [
            'provider'           => $this->provider(),
            'resource'           => $this->resource(),
            'visibility'         => $this->visibility(),
            'keep_last_releases' => $this->keepLastReleases(),
            'settings'           => $this->settings(),
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
