<?php

declare(strict_types=1);

namespace Packio\Source\Domain\ValueObject;

enum SourceProvider: string
{
    case Github           = 'github';
    case Gitlab           = 'gitlab';
    case GitlabSelfHosted = 'gitlab_self_hosted';
    case GenericGit       = 'generic_git';
    case Bitbucket        = 'bitbucket';
    case Codeberg         = 'codeberg';

    public function enabledEnvName(): string
    {
        return match ($this) {
            self::Github           => 'SOURCE_PROVIDER_GITHUB_ENABLED',
            self::Gitlab           => 'SOURCE_PROVIDER_GITLAB_ENABLED',
            self::GitlabSelfHosted => 'SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED',
            self::GenericGit       => 'SOURCE_PROVIDER_GENERIC_GIT_ENABLED',
            self::Bitbucket        => 'SOURCE_PROVIDER_BITBUCKET_ENABLED',
            self::Codeberg         => 'SOURCE_PROVIDER_CODEBERG_ENABLED',
        };
    }
}
