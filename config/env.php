<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = \dirname(__DIR__);

Dotenv::createUnsafeImmutable($rootPath, '.env.local')->safeLoad();
Dotenv::createUnsafeImmutable($rootPath, '.env')->safeLoad();
