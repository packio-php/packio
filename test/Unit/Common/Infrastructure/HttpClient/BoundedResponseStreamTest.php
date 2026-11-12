<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\BoundedResponseStream;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use PHPUnit\Framework\TestCase;

final class BoundedResponseStreamTest extends TestCase
{
    public function testChunkedResponseCannotExceedLimit(): void
    {
        $boundedResponseStream = new BoundedResponseStream(8);
        self::assertSame(4, $boundedResponseStream->write('1234'));
        self::assertSame(4, $boundedResponseStream->write('5678'));
        self::assertSame('12345678', (string) $boundedResponseStream);
        $boundedResponseStream->seek(0, SEEK_END);
        $this->expectException(UpstreamHttpException::class);
        $boundedResponseStream->write('9');
    }
}
