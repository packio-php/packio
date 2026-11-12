<?php

declare(strict_types=1);

namespace Packio\Application\Release;

use function dirname;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function ltrim;
use function sprintf;
use function trim;

final readonly class AppReleaseVersion
{
    private string $basePath;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->basePath = dirname(__DIR__, 4);
    }

    /** @return array{version: string, number: string, channel: string, sha: string, branch: string, built_at: string, distance: int, dirty: bool, tag: string, display: string} */
    public function toArray(): array
    {
        $payload = $this->releaseFilePayload() ?? new AppReleaseMetadataGenerator($this->basePath)->generate();
        $version = $this->stringValue($payload, 'version');
        if ('' === $version) {
            $version = $this->stringValue($payload, 'number');
        }

        if ('' === $version) {
            $version = '0.0.0-dev.0';
        }

        $sha     = $this->stringValue($payload, 'sha');
        $display = $this->stringValue($payload, 'display');
        if ('' === $display) {
            $display = $version . ('' !== $sha ? sprintf(' (%s)', $sha) : '');
        }

        return [
            'version'  => $version,
            'number'   => $version,
            'channel'  => $this->stringValue($payload, 'channel', 'dev'),
            'sha'      => $sha,
            'branch'   => $this->stringValue($payload, 'branch'),
            'built_at' => $this->stringValue($payload, 'built_at'),
            'distance' => $this->intValue($payload, 'distance'),
            'dirty'    => $this->boolValue($payload, 'dirty'),
            'tag'      => $this->stringValue($payload, 'tag'),
            'display'  => $display,
        ];
    }

    /** @return null|array<string, mixed> */
    private function releaseFilePayload(): ?array
    {
        $path = $this->releaseFilePath();
        if ('' === $path || ! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function releaseFilePath(): string
    {
        $configured = trim((string) ($this->config['release']['file'] ?? 'data/build/release.json'));
        if ('' === $configured) {
            return '';
        }

        if ('/' === $configured[0]) {
            return $configured;
        }

        return $this->basePath . '/' . ltrim($configured, '/');
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    /** @param array<string, mixed> $payload */
    private function intValue(array $payload, string $key): int
    {
        $value = $payload[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }

    /** @param array<string, mixed> $payload */
    private function boolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? false;

        return is_bool($value) && $value;
    }
}
