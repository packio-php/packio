<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Git;

use Packio\Publication\Infrastructure\Git\GitHttpAuthHeader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

final class GitHttpAuthHeaderTest extends TestCase
{
    #[Test]
    public function buildsGitHttpAuthorizationHeaderValues(): void
    {
        self::assertSame(
            'Authorization: Basic ' . base64_encode('oauth2:source-token'),
            GitHttpAuthHeader::basic('oauth2', 'source-token'),
        );
        self::assertSame('Authorization: Bearer source-token', GitHttpAuthHeader::bearer('source-token'));
        self::assertSame('Authorization: token source-token', GitHttpAuthHeader::token('source-token'));
    }
}
