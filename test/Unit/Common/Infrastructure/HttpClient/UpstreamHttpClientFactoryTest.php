<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use Common\Infrastructure\HttpClient\OutboundUrlPolicyFactory;
use Common\Infrastructure\HttpClient\UpstreamHttpClientFactory;
use Common\Infrastructure\Resource\ResourceLimits;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class UpstreamHttpClientFactoryTest extends TestCase
{
    public function testProductionClientBlocksPrivateDestinationsBeforeNetworkAccess(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(3))->method('get')->willReturnMap([
            [OutboundUrlPolicy::class, new OutboundUrlPolicy()],
            [ResourceLimits::class, new ResourceLimits()],
        ]);
        $client = new UpstreamHttpClientFactory()($container);
        $this->expectException(InvalidArgumentException::class);
        $client->request('GET', 'https://127.0.0.1/private');
    }

    public function testPolicyFactoryReadsOnlyInstallationConfiguration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('config')->willReturn([
            'outbound_http' => [
                'endpoints' => [
                    'https://git.company.test:8443' => ['10.0.0.9'],
                ],
            ],
        ]);
        self::assertSame(['10.0.0.9'], new OutboundUrlPolicyFactory()($container)->resolve('https://git.company.test:8443/repo')['addresses']);
    }
}
