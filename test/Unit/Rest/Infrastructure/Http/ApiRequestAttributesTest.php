<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use Packio\Rest\Application\Security\ApiScopeEvaluator;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiRequestAttributesTest extends TestCase
{
    #[Test]
    public function tokenAllowsFailsClosedWithoutAnApiToken(): void
    {
        self::assertFalse(ApiRequestAttributes::tokenAllows(new ServerRequest(), new ApiScopeEvaluator(), 'sources:write'));
    }
}
