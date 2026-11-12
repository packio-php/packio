<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Service;

use Packio\Registry\Application\Service\RegistryTokenPermissionEvaluator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Rbac\PermissionMatcher;

#[CoversClass(RegistryTokenPermissionEvaluator::class)]
final class RegistryTokenPermissionEvaluatorTest extends TestCase
{
    #[Test]
    public function itAllowsExactPermission(): void
    {
        self::assertTrue($this->evaluator()->allows('packages.read.private', [
            'packages.read.private',
        ]));
    }

    #[Test]
    public function itAllowsWildcardPermissionPatternsLikeRbac(): void
    {
        $evaluator = $this->evaluator();

        self::assertTrue($evaluator->allows('packages.read.private', [
            'packages.read.*',
        ]));
        self::assertTrue($evaluator->allows('packages.read.private.metadata', [
            'packages.read.*',
        ]));
        self::assertTrue($evaluator->allows('packages.read.private', [
            'packages.*.private',
        ]));
    }

    #[Test]
    public function itDeniesMissingOrTooShallowPermissions(): void
    {
        $evaluator = $this->evaluator();

        self::assertFalse($evaluator->allows('packages.read.private', []));
        self::assertFalse($evaluator->allows('packages.read', [
            'packages.read.*',
        ]));
    }

    #[Test]
    public function itIgnoresEmptyPermissionPatterns(): void
    {
        self::assertFalse($this->evaluator()->allows('packages.read.private', [
            '',
            '   ',
        ]));
    }

    #[Test]
    public function itDeniesEmptyRequestedPermission(): void
    {
        self::assertFalse($this->evaluator()->allows('   ', [
            'packages.read.*',
        ]));
    }

    private function evaluator(): RegistryTokenPermissionEvaluator
    {
        return new RegistryTokenPermissionEvaluator(new PermissionMatcher());
    }
}
