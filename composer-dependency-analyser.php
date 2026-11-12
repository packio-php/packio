<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToScan(__DIR__ . '/config', isDev: false)
    ->addPathToScan(__DIR__ . '/bin', isDev: false)
    ->addPathToScan(__DIR__ . '/data/db/cycle/migrations', isDev: false)
    // Cycle loads migration files dynamically; they are not Composer PSR-4 classes.
    ->ignoreUnknownClasses([
        'Migration\\BackfillOrganizationDeletionRunIds',
        'Migration\\BackfillSourceDeletionRunIds',
        'Migration\\OrmDefault5ed7ab1bb79ef592c5fe380cd812392f',
        // Optional providers in config/config.php are guarded by class_exists().
        'Mezzio\\Swoole\\ConfigProvider',
        'Mezzio\\Tooling\\ConfigProvider',
    ])
    // PDO and its PostgreSQL driver are used by Cycle's configured production DB.
    ->ignoreErrorsOnExtension('ext-pdo', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnExtension('ext-pdo_pgsql', [ErrorType::UNUSED_DEPENDENCY])
    // Keep the existing runtime extension contract for SDK/XML/TLS consumers.
    ->ignoreErrorsOnExtension('ext-dom', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-iconv', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-openssl', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-simplexml', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-xmlwriter', [ErrorType::UNUSED_DEPENDENCY])
    // The schema migration CLI is conditionally registered by Cycle's provider.
    ->ignoreErrorsOnPackage('cycle/schema-migrations-generator', [ErrorType::UNUSED_DEPENDENCY])
    // ServiceManager lazy_services in config/autoload/dependencies.global.php.
    ->ignoreErrorsOnPackage('friendsofphp/proxy-manager-lts', [ErrorType::UNUSED_DEPENDENCY])
    // CLI entrypoint and installation plugin are invoked outside scanned PHP code.
    ->ignoreErrorsOnPackage('laminas/laminas-cli', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('laminas/laminas-component-installer', [ErrorType::UNUSED_DEPENDENCY])
    // Laminas CLI ContainerResolver uses ArrayUtils for application config merging.
    ->ignoreErrorsOnPackage('laminas/laminas-stdlib', [ErrorType::UNUSED_DEPENDENCY]);
