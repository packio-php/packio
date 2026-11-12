<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Service;

use function trim;

final readonly class SourceAuthorizationStrategyResolver
{
    public function defaultFor(string $provider): string
    {
        return $this->normalize($provider, 'token');
    }

    public function normalize(string $provider, ?string $authStrategy): string
    {
        $normalized = trim((string) $authStrategy);
        if ('' === $normalized) {
            $normalized = 'token';
        }

        return match ($provider) {
            'github'             => 'token' === $normalized ? 'github_token' : $normalized,
            'gitlab'             => 'token' === $normalized ? 'gitlab_token' : $normalized,
            'gitlab_self_hosted' => 'token' === $normalized ? 'gitlab_self_hosted_token' : $normalized,
            'generic_git'        => match ($normalized) {
                'token' => 'generic_git_token',
                'none'  => 'generic_git_none',
                default => $normalized,
            },
            'bitbucket'          => 'token' === $normalized ? 'bitbucket_token' : $normalized,
            'codeberg'           => 'token' === $normalized ? 'codeberg_token' : $normalized,
            default              => $normalized,
        };
    }
}
