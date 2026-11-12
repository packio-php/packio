<?php

declare(strict_types=1);

use Common\Infrastructure\HttpClient\HttpClientFactory;
use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use Common\Infrastructure\HttpClient\OutboundUrlPolicyFactory;
use Common\Infrastructure\HttpClient\SafeGitEnvironment;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Infrastructure\HttpClient\UpstreamHttpClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;

return [
    ConfigAbstractFactory::class => [
        SafeGitEnvironment::class => [OutboundUrlPolicy::class],
    ],
    'dependencies'               => [
        'factories' => [
            Client::class             => HttpClientFactory::class,
            UpstreamHttpClient::class => UpstreamHttpClientFactory::class,
            OutboundUrlPolicy::class  => OutboundUrlPolicyFactory::class,
            SafeGitEnvironment::class => ConfigAbstractFactory::class,
        ],
        'aliases'   => [
            ClientInterface::class => Client::class,
        ],
    ],
];
