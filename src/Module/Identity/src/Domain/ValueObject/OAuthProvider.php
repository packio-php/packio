<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\ValueObject;

enum OAuthProvider: string
{
    case Github    = 'github';
    case Gitlab    = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Codeberg  = 'codeberg';

    public function label(): string
    {
        return match ($this) {
            self::Github    => 'GitHub',
            self::Gitlab    => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Codeberg  => 'Codeberg',
        };
    }
}
