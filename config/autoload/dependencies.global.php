<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\Proxy\LazyServiceFactory;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Middleware\ErrorResponseGenerator;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Packio\Application\Error\AuthorizationExceptionResponseFactory;
use Packio\Application\Error\HttpErrorLoggingDelegator;
use Packio\Application\Error\InertiaErrorResponseGenerator;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Application\Runtime\LongRunningHttpApplication;
use Packio\Application\Runtime\LongRunningHttpApplicationFactory;
use Packio\Application\Security\Authentication\CsrfAwareAuthManagerDelegator;
use Packio\Application\Service\InitialThemeResolver;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Facade\OrganizationApiFacade;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use Psr\Http\Message\StreamFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

return [
    'dependencies'               => [
        'aliases'            => [
            ErrorResponseGenerator::class => InertiaErrorResponseGenerator::class,
        ],
        'invokables'         => [],
        'factories'          => [
            InertiaErrorResponseGenerator::class => ConfigAbstractFactory::class,
            LongRunningHttpApplication::class    => LongRunningHttpApplicationFactory::class,
        ],
        'abstract_factories' => [
            ConfigAbstractFactory::class,
        ],
        'lazy_services'      => [
            'class_map'          => [
                OrganizationApiFacade::class => OrganizationApiFacadeInterface::class,
            ],
            'proxies_target_dir' => 'data/cache/proxies',
            'proxies_namespace'  => 'PackioGeneratedProxy',
            'write_proxy_files'  => true,
        ],
        'delegators'         => [
            ErrorHandler::class           => [
                HttpErrorLoggingDelegator::class,
            ],
            OrganizationApiFacade::class  => [
                LazyServiceFactory::class,
            ],
            AuthManagerInterface::class   => [
                CsrfAwareAuthManagerDelegator::class,
            ],
        ],
    ],
    ConfigAbstractFactory::class => [
        InertiaErrorResponseGenerator::class      => [
            InertiaFactoryInterface::class,
            InertiaVersionProviderInterface::class,
            'config.debug',
            TemplateRendererInterface::class,
            BrandingConfig::class,
            InitialThemeResolver::class,
            AuthorizationExceptionResponseFactory::class,
        ],
        OperationalProbeDispatchMiddleware::class => [
            ImplicitHeadRouterAdapter::class,
            RestrictRegistrySubdomainMiddleware::class,
            StreamFactoryInterface::class,
            DispatchMiddleware::class,
        ],
    ],
];
