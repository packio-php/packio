<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Psr\Container\ContainerInterface;

final class UpstreamHttpClientFactory
{
    public function __invoke(ContainerInterface $container): UpstreamHttpClient
    {
        $curlHandler = new CurlHandler();

        return new UpstreamHttpClient(new Client([
            'handler'         => HandlerStack::create(new SafeHttpHandler($container->get(OutboundUrlPolicy::class), $curlHandler(...), $container->get(ResourceLimits::class))),
            'allow_redirects' => false,
        ]), $container->get(ResourceLimits::class));
    }
}
