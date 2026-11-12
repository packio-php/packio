<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Packio\Application\Runtime\LongRunningHttpApplication;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

\chdir(\dirname(__DIR__));

require 'vendor/autoload.php';

/**
 * FrankenPHP worker bootstrap.
 *
 * This keeps the application bootstrapped in-memory and processes each request through
 * the same Mezzio application instance.
 */
(function(): void {
    if (! \function_exists('frankenphp_handle_request')) {
        throw new RuntimeException('This entrypoint is intended to be used with FrankenPHP worker mode');
    }

    /** @var ContainerInterface $container */
    $container = require 'config/container.php';

    /** @var EmitterInterface $responseEmitter */
    $responseEmitter = $container->get(EmitterInterface::class);

    /** @var LongRunningHttpApplication $application */
    $application = $container->get(LongRunningHttpApplication::class);
    $application->bootstrap('frankenphp');

    $handler = static function() use ($application, $responseEmitter): void {
        $application->handleRequest(
            ServerRequestFactory::fromGlobals(),
            static function(ResponseInterface $response) use ($responseEmitter): void {
                $responseEmitter->emit($response);
            },
        );
    };

    do {
        $keepRunning = \frankenphp_handle_request($handler);
    } while ($keepRunning);
})();
