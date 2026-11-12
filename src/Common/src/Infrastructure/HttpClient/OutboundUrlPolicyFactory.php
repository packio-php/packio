<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Psr\Container\ContainerInterface;

final class OutboundUrlPolicyFactory
{
    public function __invoke(ContainerInterface $container): OutboundUrlPolicy
    {
        return new OutboundUrlPolicy($container->get('config')['outbound_http']['endpoints'] ?? []);
    }
}
