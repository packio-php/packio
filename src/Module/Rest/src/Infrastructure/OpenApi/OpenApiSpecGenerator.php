<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\OpenApi;

use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;

use function array_filter;
use function array_values;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;

final class OpenApiSpecGenerator
{
    private const string ADMIN_TAG = 'Admin';

    private const array ADMIN_SCHEMA_NAMES = [
        'AdminOrganizationUpdateRequest',
        'AdminSettingsUpdateRequest',
        'AdminUserUpdateRequest',
    ];

    /** @var null|array<string, mixed> the spec depends only on code, so caching it per worker is safe between requests */
    private ?array $spec = null;

    /**
     * @param array<string, mixed> $docsConfig
     */
    public function __construct(private readonly array $docsConfig = []) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(bool $includeAdmin = false): array
    {
        $spec = $this->spec ??= $this->scan();
        if (null === $spec) {
            return [];
        }

        return $includeAdmin ? $spec : $this->withoutAdminSection($spec);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function scan(): ?array
    {
        $generator = new Generator();
        $generator->setConfig([
            'generator.ignoreOtherAttributes' => true,
        ]);

        $scanPaths = $this->docsConfig['scan_paths'] ?? [dirname(__DIR__, 2)];
        $scanPaths = is_array($scanPaths) ? $scanPaths : [dirname(__DIR__, 2)];

        $openApi = $generator->generate($scanPaths, validate: false);
        if (! $openApi instanceof OpenApi) {
            return null;
        }

        $decoded = json_decode($openApi->toJson(JSON_UNESCAPED_SLASHES), true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $openApi
     *
     * @return array<string, mixed>
     */
    private function withoutAdminSection(array $openApi): array
    {
        if (isset($openApi['paths']) && is_array($openApi['paths'])) {
            foreach ($openApi['paths'] as $path => $pathItem) {
                if (
                    is_string($path)
                    && (str_starts_with($path, '/api/v1/admin') || $this->pathItemHasAdminTag($pathItem))
                ) {
                    unset($openApi['paths'][$path]);
                }
            }
        }

        if (isset($openApi['tags']) && is_array($openApi['tags'])) {
            $openApi['tags'] = array_values(array_filter(
                $openApi['tags'],
                static fn (mixed $tag): bool => ! is_array($tag) || ($tag['name'] ?? null) !== self::ADMIN_TAG,
            ));
        }

        if (isset($openApi['components']['schemas']) && is_array($openApi['components']['schemas'])) {
            foreach (self::ADMIN_SCHEMA_NAMES as $schemaName) {
                unset($openApi['components']['schemas'][$schemaName]);
            }
        }

        return $openApi;
    }

    private function pathItemHasAdminTag(mixed $pathItem): bool
    {
        if (! is_array($pathItem)) {
            return false;
        }

        foreach ($pathItem as $operation) {
            if (! is_array($operation)) {
                continue;
            }

            $tags = $operation['tags'] ?? [];
            if (is_array($tags) && in_array(self::ADMIN_TAG, $tags, true)) {
                return true;
            }
        }

        return false;
    }
}
