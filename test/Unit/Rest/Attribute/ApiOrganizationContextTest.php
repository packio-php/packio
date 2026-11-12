<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Attribute;

use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Infrastructure\Middleware\ApiAuditMiddleware;
use Packio\Rest\Infrastructure\Middleware\ApiOrganizationContextMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiOrganizationContext::class)]
final class ApiOrganizationContextTest extends TestCase
{
    #[Test]
    public function registersOrganizationContextMiddlewareAndUniqueAuditMiddleware(): void
    {
        $apiOrganizationContext = new ApiOrganizationContext();

        self::assertSame([ApiOrganizationContextMiddleware::class], $apiOrganizationContext->getMiddleware());
        self::assertSame(
            [
                'packio.api.audit' => ApiAuditMiddleware::class,
            ],
            $apiOrganizationContext->getUniqueMiddleware(),
        );
        self::assertSame([], $apiOrganizationContext->getDefaults());
        self::assertSame([
            'existing' => 'default',
        ], $apiOrganizationContext->mergeDefaults([
            'existing' => 'default',
        ]));
    }
}
