<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Packio\Application\Runtime\LongRunningHttpApplication;
use Packio\Application\Runtime\Psr7RoadRunnerHttpTransport;
use Packio\Application\Runtime\RoadRunnerHttpEntrypoint;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

ob_start(static function (string $output): string {
    if ('' !== $output) {
        fwrite(STDERR, "Unexpected PHP worker stdout was suppressed.\n");
    }

    return '';
}, 1);

\chdir(\dirname(__DIR__));

require 'vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require 'config/container.php';

/** @var LongRunningHttpApplication $application */
$application = $container->get(LongRunningHttpApplication::class);

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

$worker = new PSR7Worker(
    Worker::create(),
    new ServerRequestFactory(),
    new StreamFactory(),
    new UploadedFileFactory(),
);

new RoadRunnerHttpEntrypoint($application, $logger)
    ->run(new Psr7RoadRunnerHttpTransport($worker))
;
