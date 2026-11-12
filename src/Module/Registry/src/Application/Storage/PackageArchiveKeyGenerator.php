<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Storage;

use InvalidArgumentException;

use function hash;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

final readonly class PackageArchiveKeyGenerator
{
    private const string COMPOSER_NORMALIZED_DEV_MASTER = '9999999-dev';

    public function __construct(private string $prefix) {}

    public function key(
        string $organizationId,
        string $packageName,
        string $version,
        string $reference,
        string $format = 'zip',
    ): string {
        $packageName = strtolower(trim($packageName));

        if (1 !== preg_match('/\A([a-z0-9_.-]+)\/([a-z0-9_.-]+)\z/', $packageName, $matches)) {
            throw new InvalidArgumentException('Package archive key requires a canonical vendor/package name.');
        }

        return sprintf(
            '%s/org/%s/dist/%s/%s/%s_%s.%s',
            $this->prefix,
            $this->pathSegment($organizationId, 'organization id'),
            $matches[1],
            $matches[2],
            $this->versionSegment($version),
            $this->pathSegment($reference, 'source reference'),
            $this->formatSegment($format),
        );
    }

    public function organizationDistPrefix(string $organizationId): string
    {
        return sprintf(
            '%s/org/%s/dist/',
            $this->prefix,
            $this->pathSegment($organizationId, 'organization id'),
        );
    }

    public function packageDistPrefix(string $organizationId, string $packageName): string
    {
        $packageName = strtolower(trim($packageName));

        if (1 !== preg_match('/\A([a-z0-9_.-]+)\/([a-z0-9_.-]+)\z/', $packageName, $matches)) {
            throw new InvalidArgumentException('Package archive key requires a canonical vendor/package name.');
        }

        return sprintf(
            '%s%s/%s/',
            $this->organizationDistPrefix($organizationId),
            $matches[1],
            $matches[2],
        );
    }

    private function versionSegment(string $version): string
    {
        $version = trim($version);

        if (self::COMPOSER_NORMALIZED_DEV_MASTER === $version) {
            return 'dev-master';
        }

        if ('' === $version || str_contains($version, '/') || str_contains($version, '\\')) {
            return 'version-' . hash('sha1', $version);
        }

        return $this->pathSegment($version, 'version');
    }

    private function pathSegment(string $value, string $name): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgumentException(sprintf('Package archive key requires a non-empty %s.', $name));
        }

        if (1 !== preg_match('/\A[A-Za-z0-9._~+-]+\z/', $value)) {
            return hash('sha1', $value);
        }

        return $value;
    }

    private function formatSegment(string $format): string
    {
        $format = strtolower(trim($format, ". \t\n\r\0\x0B"));

        if ('' === $format) {
            throw new InvalidArgumentException('Package archive key requires a non-empty archive format.');
        }

        if (1 !== preg_match('/\A[a-z0-9]+\z/', $format)) {
            throw new InvalidArgumentException('Package archive format may only contain lowercase letters and numbers.');
        }

        return $format;
    }
}
