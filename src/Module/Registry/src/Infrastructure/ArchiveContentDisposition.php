<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure;

use function preg_replace;
use function rawurlencode;
use function trim;

final class ArchiveContentDisposition
{
    public static function attachment(string $fileName): string
    {
        $safeName = preg_replace('/[\x00-\x1f\x7f\\\\\/]/', '_', $fileName) ?? 'archive.zip';
        $fallback = preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeName) ?? 'archive.zip';
        if ('' === trim($fallback, '.')) {
            $fallback = 'archive.zip';
        }
        $header = 'attachment; filename="' . $fallback . '"';

        return $safeName === $fallback ? $header : $header . "; filename*=UTF-8''" . rawurlencode($safeName);
    }
}
