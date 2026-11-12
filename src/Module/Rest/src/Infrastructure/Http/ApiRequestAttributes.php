<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Http;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Rest\Application\Security\ApiScopeEvaluator;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_values;
use function in_array;
use function str_starts_with;
use function trim;

final class ApiRequestAttributes
{
    public const string ACCESS_TOKEN   = 'packio.api.access_token';
    public const string REQUIRED_SCOPE = 'packio.api.required_scope';

    /**
     * Admin grants are only effective while the token owner is still an admin.
     *
     * @return list<string>
     */
    public static function effectiveScopes(ServerRequestInterface $serverRequest, ApiAccessTokenDto $apiAccessTokenDto): array
    {
        $user = RequestContextAttributes::currentUser($serverRequest);
        if ($user instanceof UserDto && in_array('admin', $user->roles, true)) {
            return $apiAccessTokenDto->scopes;
        }

        return array_values(array_filter(
            $apiAccessTokenDto->scopes,
            static function(string $scope): bool {
                $scope = trim($scope);

                return '*' !== $scope && 'admin' !== $scope && ! str_starts_with($scope, 'admin:');
            },
        ));
    }

    /**
     * Fails closed: a request without an API token is not allowed.
     */
    public static function tokenAllows(ServerRequestInterface $serverRequest, ApiScopeEvaluator $apiScopeEvaluator, string $scope): bool
    {
        $token = $serverRequest->getAttribute(self::ACCESS_TOKEN);

        return $token instanceof ApiAccessTokenDto
            && $apiScopeEvaluator->allows($scope, self::effectiveScopes($serverRequest, $token));
    }
}
