<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\HttpClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class HttpClientFactoryTest extends TestCase
{
    public function testClientHasDefaultTimeouts(): void
    {
        $client = new HttpClientFactory()($this->createStub(ContainerInterface::class));

        self::assertSame(10.0, $client->getConfig('timeout'));
        self::assertSame(5.0, $client->getConfig('connect_timeout'));
    }
}
