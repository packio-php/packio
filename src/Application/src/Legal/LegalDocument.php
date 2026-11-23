<?php

declare(strict_types=1);

namespace Packio\Application\Legal;

use function substr;

enum LegalDocument: string
{
    case Privacy = 'privacy';
    case Terms   = 'terms';

    public static function fromPath(string $path): ?self
    {
        return '/' === ($path[0] ?? '') ? self::tryFrom(substr($path, 1)) : null;
    }

    public function path(): string
    {
        return '/' . $this->value;
    }

    public function title(): string
    {
        return match ($this) {
            self::Privacy => 'Privacy Policy',
            self::Terms   => 'Terms of Service',
        };
    }
}
