<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth\Client;

use function array_is_list;
use function array_unique;
use function array_values;
use function is_array;

final readonly class OAuthScopeResolver
{
    /**
     * @param array<string, mixed> $config
     * @param list<string>         $defaultScopes
     *
     * @return list<string>
     */
    public function sourceScopes(array $config, array $defaultScopes): array
    {
        $sourceScopes = $config['source_scopes'] ?? null;

        if (is_array($sourceScopes) && array_is_list($sourceScopes)) {
            return $sourceScopes;
        }

        return $defaultScopes;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $defaultScopes
     * @param list<string>         $additionalScopes
     *
     * @return list<string>
     */
    public function authorizationScopes(array $config, array $defaultScopes, array $additionalScopes = []): array
    {
        $configScopes = is_array($config['scopes'] ?? null) && array_is_list($config['scopes'])
            ? $config['scopes']
            : $defaultScopes;

        return $this->unique([...$configScopes, ...$additionalScopes]);
    }

    /**
     * @param list<string> $scopes
     *
     * @return list<string>
     */
    public function unique(array $scopes): array
    {
        return array_values(array_unique($scopes));
    }
}
