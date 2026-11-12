<?php

declare(strict_types=1);

$projectRoot = \realpath(\dirname(__DIR__, 2));
if (false === $projectRoot) {
    $projectRoot = \dirname(__DIR__, 2);
}

require $projectRoot . '/vendor/autoload.php';

if (! \chdir($projectRoot)) {
    throw new RuntimeException('Unable to set the Packio project root as the test working directory.');
}

// Keep runtime artifacts off developer machine defaults and make test runs repeatable.
\putenv('APP_ENV=test');
\putenv('APP_URL=https://packio.us');
\putenv('BRAND_DOMAIN=packio.us');
\putenv('BRAND_REGISTRY_BASE_DOMAIN=packio.us');
$_ENV['APP_ENV']                       = 'test';
$_SERVER['APP_ENV']                    = 'test';
$_ENV['APP_URL']                       = 'https://packio.us';
$_SERVER['APP_URL']                    = 'https://packio.us';
$_ENV['BRAND_DOMAIN']                  = 'packio.us';
$_SERVER['BRAND_DOMAIN']               = 'packio.us';
$_ENV['BRAND_REGISTRY_BASE_DOMAIN']    = 'packio.us';
$_SERVER['BRAND_REGISTRY_BASE_DOMAIN'] = 'packio.us';

\putenv('DEFAULT_DB_CONNECTION=postgres');
$_ENV['DEFAULT_DB_CONNECTION']    = 'postgres';
$_SERVER['DEFAULT_DB_CONNECTION'] = 'postgres';
