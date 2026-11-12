<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use RuntimeException;

use function sprintf;

final class SourceProviderInstanceException extends RuntimeException
{
    private const int ERROR_NOT_FOUND = 1;
    private const int ERROR_IN_USE    = 2;
    private const int ERROR_FORBIDDEN = 3;

    public static function unsupportedProviderType(string $providerType): self
    {
        return new self(sprintf('Unsupported source provider instance type "%s".', $providerType));
    }

    public static function unsupportedAuthMode(string $authMode): self
    {
        return new self(sprintf('Unsupported source provider instance auth mode "%s".', $authMode));
    }

    public static function invalidBaseUrl(): self
    {
        return new self('Source provider instance base URL must be an HTTPS origin URL.');
    }

    public static function duplicate(): self
    {
        return new self('A source provider instance with this provider type and base URL already exists in the organization.', self::ERROR_IN_USE);
    }

    public static function notFound(): self
    {
        return new self('Source provider instance was not found.', self::ERROR_NOT_FOUND);
    }

    public static function inUse(): self
    {
        return new self('Source provider instance cannot be deleted while sources or authorizations depend on it.', self::ERROR_IN_USE);
    }

    public static function forbidden(): self
    {
        return new self('Owner actor context is required.', self::ERROR_FORBIDDEN);
    }

    public function isForbidden(): bool
    {
        return self::ERROR_FORBIDDEN === $this->getCode();
    }

    public function isNotFound(): bool
    {
        return self::ERROR_NOT_FOUND === $this->getCode();
    }

    public function isInUse(): bool
    {
        return self::ERROR_IN_USE === $this->getCode();
    }

    public static function oauthClientCredentialsRequired(): self
    {
        return new self('OAuth client ID and client secret are required for OAuth-capable source provider instances.');
    }
}
