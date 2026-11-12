<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Dto;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

final readonly class OAuthState
{
    public const string ACTION_LOGIN          = 'login';
    public const string ACTION_CONNECT_SOURCE = 'connect_source';

    public function __construct(
        public string $state,
        public string $action = self::ACTION_LOGIN,
        public ?string $organizationSlug = null,
        public ?string $provider = null
    ) {}

    public static function forLogin(string $state): self
    {
        return new self(state: $state, action: self::ACTION_LOGIN);
    }

    public static function forConnectSource(string $state, ?string $organizationSlug = null, ?string $provider = null): self
    {
        return new self(
            state: $state,
            action: self::ACTION_CONNECT_SOURCE,
            organizationSlug: $organizationSlug,
            provider: $provider,
        );
    }

    public function encode(): string
    {
        $payload = [
            's' => $this->state,
            'a' => $this->action,
        ];

        if (null !== $this->organizationSlug) {
            $payload['o'] = $this->organizationSlug;
        }

        if (null !== $this->provider) {
            $payload['p'] = $this->provider;
        }

        return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function decode(string $encoded): ?self
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            return null;
        }

        $state  = $payload['s'] ?? '';
        $action = $payload['a'] ?? 'login';

        $organizationSlug = $payload['o'] ?? null;
        $provider         = $payload['p'] ?? null;

        if (
            ! is_string($state) || '' === $state
            || ! is_string($action)
            || (null !== $organizationSlug && ! is_string($organizationSlug))
            || (null !== $provider && ! is_string($provider))
        ) {
            return null;
        }

        return new self(
            state: $state,
            action: $action,
            organizationSlug: $organizationSlug,
            provider: $provider,
        );
    }

    public function isConnectSourceAction(): bool
    {
        return self::ACTION_CONNECT_SOURCE === $this->action;
    }
}
