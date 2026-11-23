<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Resource;

use Packio\Application\Infrastructure\Resource\UserSignInResource;
use Packio\Identity\Api\Dto\UserSignInDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserSignInResourceTest extends TestCase
{
    #[Test]
    public function itMapsDtoToSnakeCaseArray(): void
    {
        $userSignInDto = new UserSignInDto('id-1', 'github', 'GitHub', '2026-11-22T10:00:00+00:00', '203.0.113.7', 'Mozilla/5.0');

        self::assertSame([
            'id'           => 'id-1',
            'method'       => 'github',
            'method_label' => 'GitHub',
            'occurred_at'  => '2026-11-22T10:00:00+00:00',
            'ip'           => '203.0.113.7',
            'user_agent'   => 'Mozilla/5.0',
        ], UserSignInResource::fromDto($userSignInDto));
    }
}
