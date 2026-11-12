<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use function preg_match;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function substr;

/**
 * Validates and normalizes Composer package vendor/package segments.
 *
 * Handles:
 * - `~dev` suffix (e.g., `vendor/package~dev` → `vendor/package`)
 * - Vendor and package name validation
 * - Canonical package name construction
 */
final readonly class VendorPackageInput
{
    private const string VENDOR_REGEX   = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/?$/i';
    private const string PACKAGE_REGEX  = '/^[a-z0-9]([_.-]?[a-z0-9]+)*$/i';
    private const string DEV_SUFFIX     = '~dev';
    private const int DEV_SUFFIX_LENGTH = 4;

    public string $canonical;

    /**
     * @param list<string> $errors
     */
    private function __construct(public string $vendor, public string $package, public array $errors = [])
    {
        $this->canonical = $this->vendor . '/' . $this->package;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    /**
     * Create from raw route segments (vendor, package).
     *
     * Handles normalization:
     * - Strips `~dev` suffix from the package segment (Composer uses this for dev versions).
     * - Validates vendor and package name format.
     */
    public static function fromSegments(string $vendor, string $package): self
    {
        $errors = [];

        // Normalize package segment: remove ~dev suffix
        $package = self::normalizePackageSegment($package);

        // Validate vendor
        $cleanVendor = self::normalizeVendor($vendor);
        if (! self::isValidVendor($cleanVendor)) {
            $errors[] = sprintf('Invalid vendor name: "%s".', $vendor);
        }

        // Validate package
        if (! self::isValidPackage($package)) {
            $errors[] = sprintf('Invalid package name: "%s".', $package);
        }

        return new self($cleanVendor, $package, $errors);
    }

    /**
     * Strip `~dev` suffix from a package segment.
     *
     * Composer sends `/p2/vendor/package~dev` for dev version metadata requests.
     */
    public static function normalizePackageSegment(string $package): string
    {
        if (str_ends_with($package, self::DEV_SUFFIX)) {
            return substr($package, 0, -self::DEV_SUFFIX_LENGTH);
        }

        return $package;
    }

    private static function normalizeVendor(string $vendor): string
    {
        // Remove trailing slash if present (e.g., "vendor/" → "vendor")
        return '/' === $vendor ? '' : rtrim($vendor, '/');
    }

    private static function isValidVendor(string $vendor): bool
    {
        return '' !== $vendor && 1 === preg_match(self::VENDOR_REGEX, $vendor);
    }

    private static function isValidPackage(string $package): bool
    {
        return '' !== $package && 1 === preg_match(self::PACKAGE_REGEX, $package);
    }
}
