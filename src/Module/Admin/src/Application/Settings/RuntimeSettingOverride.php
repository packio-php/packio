<?php

declare(strict_types=1);

namespace Packio\Admin\Application\Settings;

use Common\Exception\PublicValidationException;

use function strtolower;
use function trim;

enum RuntimeSettingOverride: string
{
    case Inherit = 'inherit';
    case On      = 'on';
    case Off     = 'off';

    public static function fromStoredValue(?string $value): self
    {
        if (null === $value) {
            return self::Inherit;
        }

        return self::fromInput($value);
    }

    public static function fromInput(string $value): self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            self::Inherit->value => self::Inherit,
            self::On->value      => self::On,
            self::Off->value     => self::Off,
            default              => throw new PublicValidationException('Unknown runtime setting override value.'),
        };
    }

    public function booleanValue(bool $fallback): bool
    {
        return match ($this) {
            self::Inherit => $fallback,
            self::On      => true,
            self::Off     => false,
        };
    }
}
