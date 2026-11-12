<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Application\Security;

use Packio\Rest\Application\Security\ApiScopeEvaluator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiScopeEvaluator::class)]
final class ApiScopeEvaluatorTest extends TestCase
{
    /**
     * @param list<string> $grantedScopes
     */
    #[Test]
    #[DataProvider('scopeDecisions')]
    public function evaluatesApiScopeRules(string $requiredScope, array $grantedScopes, bool $expected): void
    {
        self::assertSame($expected, new ApiScopeEvaluator()->allows($requiredScope, $grantedScopes));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2: bool}>
     */
    public static function scopeDecisions(): iterable
    {
        yield 'empty required scope allows empty grants' => ['', [], true];

        yield 'empty granted scope denies protected routes' => ['read', [], false];

        yield 'exact scope match allows' => ['packages:read', ['packages:read'], true];

        yield 'read allows read endpoint' => ['read', ['read'], true];

        yield 'read allows resource read endpoint' => ['packages:read', ['read'], true];

        yield 'read does not allow write endpoint' => ['write', ['read'], false];

        yield 'write allows read endpoint' => ['read', ['write'], true];

        yield 'write allows write endpoint' => ['write', ['write'], true];

        yield 'write allows resource read endpoint' => ['packages:read', ['write'], true];

        yield 'write allows resource write endpoint' => ['packages:write', ['write'], true];

        yield 'package read does not allow package write' => ['packages:write', ['packages:read'], false];

        yield 'package write allows exact package write' => ['packages:write', ['packages:write'], true];

        yield 'package write does not imply package read' => ['packages:read', ['packages:write'], false];

        yield 'source wildcard allows source read' => ['sources:read', ['sources:*'], true];

        yield 'source wildcard allows source write' => ['sources:write', ['sources:*'], true];

        yield 'source wildcard does not allow package read' => ['packages:read', ['sources:*'], false];

        yield 'admin allows admin root scope' => ['admin', ['admin'], true];

        yield 'admin allows admin child scope' => ['admin:settings', ['admin'], true];

        yield 'admin wildcard allows admin child scope' => ['admin:settings', ['admin:*'], true];

        yield 'admin wildcard does not match non-admin scope' => ['packages:read', ['admin:*'], false];

        yield 'admin child required rejects read grant' => ['admin:settings', ['read'], false];

        yield 'global wildcard allows protected route' => ['packages:write', ['*'], true];
    }
}
