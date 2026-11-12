<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Attribute;

use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Infrastructure\Middleware\ApiAuditMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiAudited::class)]
final class ApiAuditedTest extends TestCase
{
    #[Test]
    public function registersAuditMiddlewareOnlyThroughUniqueMiddleware(): void
    {
        $apiAudited = new ApiAudited();

        self::assertSame([], $apiAudited->getMiddleware());
        self::assertSame(
            [
                'packio.api.audit' => ApiAuditMiddleware::class,
            ],
            $apiAudited->getUniqueMiddleware(),
        );
        self::assertSame([], $apiAudited->getDefaults());
        self::assertSame([
            'existing' => 'default',
        ], $apiAudited->mergeDefaults([
            'existing' => 'default',
        ]));
    }
}
