<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Readme;

use function base64_decode;
use function is_string;
use function preg_replace;

final readonly class Base64FileContentDecoder
{
    public function decode(string $content): false|string
    {
        $normalizedContent = preg_replace('/\s+/', '', $content);
        if (! is_string($normalizedContent)) {
            return false;
        }

        return base64_decode($normalizedContent, true);
    }
}
