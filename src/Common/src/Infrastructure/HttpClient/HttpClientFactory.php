<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use GuzzleHttp;
use Psr\Container\ContainerInterface;

class HttpClientFactory
{
    public function __invoke(ContainerInterface $container): GuzzleHttp\Client
    {
        return new GuzzleHttp\Client([
            'handler'         => GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\CurlHandler()),
            'timeout'         => 10.0,
            'connect_timeout' => 5.0,
        ]);
    }
}
