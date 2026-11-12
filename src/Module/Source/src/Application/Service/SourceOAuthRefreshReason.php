<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceOAuthRefreshReason: string
{
    case ExpiresSoon   = 'expires_soon';
    case IdleKeepalive = 'idle_keepalive';
    case Manual        = 'manual';

    public static function fromPayload(?string $reason): self
    {
        if (null === $reason || '' === $reason) {
            return self::Manual;
        }

        return self::tryFrom($reason) ?? self::Manual;
    }
}
