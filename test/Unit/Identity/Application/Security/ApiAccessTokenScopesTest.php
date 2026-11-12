<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Security;

use Packio\Identity\Application\Security\ApiAccessTokenScopes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiAccessTokenScopes::class)]
final class ApiAccessTokenScopesTest extends TestCase
{
    #[Test]
    #[DataProvider('scopes')]
    public function recognisesKnownScopes(string $scope, bool $expected): void
    {
        self::assertSame($expected, ApiAccessTokenScopes::isKnown($scope));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function scopes(): iterable
    {
        foreach (['read', 'write', 'admin', '*', 'admin:*', 'sources:read', 'sources:write', 'sources:*', 'webhooks:read'] as $scope) {
            yield 'known ' . $scope => [$scope, true];
        }

        foreach (['admni', 'sources:delete', 'foo:read', 'read ', ''] as $scope) {
            yield 'unknown "' . $scope . '"' => [$scope, false];
        }
    }
}
