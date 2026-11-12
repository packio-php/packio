<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\ClientInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Template\TemplateRendererInterface;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Application\Error\AuthorizationExceptionResponseFactory;
use Packio\Application\Inertia\AppReleaseInertiaVersionProvider;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\InertiaSsrRenderer;
use Packio\Application\Inertia\InertiaSsrRootViewProvider;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Cli\RateLimitCleanupCommand;
use Packio\Application\Infrastructure\Handler\HealthHandler;
use Packio\Application\Infrastructure\Handler\HomeHandler;
use Packio\Application\Infrastructure\Handler\ReadyHandler;
use Packio\Application\Infrastructure\Handler\RootRedirectHandler;
use Packio\Application\Infrastructure\Handler\SitemapHandler;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use Packio\Application\Infrastructure\Readiness\ReadinessChecker;
use Packio\Application\Middleware\ApiAuthenticationAttemptRateLimitMiddleware;
use Packio\Application\Middleware\CsrfValidationMiddleware;
use Packio\Application\Middleware\HandleAuthorizationExceptionMiddleware;
use Packio\Application\Middleware\HandleInertiaRequests;
use Packio\Application\Middleware\HtmlCacheControlMiddleware;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Application\Middleware\RateLimitMiddleware;
use Packio\Application\Middleware\RefreshAuthenticatedSessionMiddleware;
use Packio\Application\Middleware\RequestSizeLimitMiddleware;
use Packio\Application\Middleware\RequireEmailVerificationMiddleware;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Application\Middleware\RequireUnbannedUserMiddleware;
use Packio\Application\Middleware\ResolveCurrentMembershipMiddleware;
use Packio\Application\Middleware\ResolveCurrentOrganizationMiddleware;
use Packio\Application\Middleware\ResolveCurrentUserMiddleware;
use Packio\Application\Middleware\ResolveOrganizationActorContextMiddleware;
use Packio\Application\Middleware\RuntimeCorsMiddleware;
use Packio\Application\Middleware\SecurityHeadersMiddleware;
use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Release\AppReleaseVersion;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Honeypot\HoneypotValidator;
use Packio\Application\Security\Honeypot\HoneypotValidatorInterface;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Http\TrustedProxyClientIpResolver;
use Packio\Application\Security\Http\TrustedProxyMatcher;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Packio\Application\Security\RateLimiter\FileRateLimiter;
use Packio\Application\Security\RateLimiter\FileRateLimiterFactory;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifier;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use Packio\Application\Service\CorsOptions;
use Packio\Application\Service\CorsOptionsFactory;
use Packio\Application\Service\InitialListViewModeResolver;
use Packio\Application\Service\InitialThemeResolver;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Application\Validation\ApiValidationFailureResponseFactoryInterface;
use Packio\Application\Validation\JsonValidationFailureResponseFactory;
use Packio\Application\Validation\MappedRequestValidationPlanResolver;
use Packio\Application\Validation\MappingErrorDetailsNormalizer;
use Packio\Application\Validation\MappingErrorResponder;
use Packio\Application\Validation\RequestAwareValidationFailureResponseFactory;
use Packio\Application\Validation\ValidationErrorKeyNormalizer;
use Packio\Application\Validation\ValidationFailureRequestPolicy;
use Packio\Application\Validation\ValidationFailureResponseDispatcher;
use Packio\Application\Validation\ValidationFailureResponseFactoryInterface;
use Packio\Application\Validation\ValidationFailureRoutePolicyResolver;
use Packio\Application\Validation\ValidationResultDetailsNormalizer;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Router\RadixRouter;
use Sirix\Mezzio\Valinor\Error\DefaultMappingErrorResponder;
use Sirix\Mezzio\Valinor\Error\MappingErrorResponderInterface;
use Yiisoft\Validator\ValidatorInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            ValidationFailureResponseFactoryInterface::class => RequestAwareValidationFailureResponseFactory::class,
            MappingErrorResponderInterface::class            => MappingErrorResponder::class,
            HoneypotValidatorInterface::class                => HoneypotValidator::class,
            ClientIpResolverInterface::class                 => TrustedProxyClientIpResolver::class,
            RateLimiterInterface::class                      => FileRateLimiter::class,
            TurnstileVerifierInterface::class                => TurnstileVerifier::class,
            RootViewProviderInterface::class                 => InertiaSsrRootViewProvider::class,
            InertiaVersionProviderInterface::class           => AppReleaseInertiaVersionProvider::class,
        ],
        'invokables' => [
            PendingEmailVerificationSession::class                      => PendingEmailVerificationSession::class,
            RequestPresentationPolicy::class                            => RequestPresentationPolicy::class,
            HealthHandler::class                                        => HealthHandler::class,
            HomeHandler::class                                          => HomeHandler::class,
            HoneypotValidator::class                                    => HoneypotValidator::class,
            HoneypotFieldNameGenerator::class                           => HoneypotFieldNameGenerator::class,
            CsrfTokenManager::class                                     => CsrfTokenManager::class,
            HtmlCacheControlMiddleware::class                           => HtmlCacheControlMiddleware::class,
            SecurityHeadersMiddleware::class                            => SecurityHeadersMiddleware::class,
            RefreshAuthenticatedSessionMiddleware::class                => RefreshAuthenticatedSessionMiddleware::class,
            StripPackageMappingErrorInertiaDecorationMiddleware::class  => StripPackageMappingErrorInertiaDecorationMiddleware::class,
            InitialListViewModeResolver::class                          => InitialListViewModeResolver::class,
            InitialThemeResolver::class                                 => InitialThemeResolver::class,
            SafeRedirectTarget::class                                   => SafeRedirectTarget::class,
        ],
        'factories'  => [
            ImplicitHeadRouterAdapter::class                        => ConfigAbstractFactory::class,
            ReadyHandler::class                                     => ConfigAbstractFactory::class,
            ReadinessChecker::class                                 => ConfigAbstractFactory::class,
            ApiAuthenticationAttemptLimiter::class                  => ConfigAbstractFactory::class,
            ApiAuthenticationAttemptRateLimitMiddleware::class      => ConfigAbstractFactory::class,
            RootRedirectHandler::class                              => ConfigAbstractFactory::class,
            SitemapHandler::class                                   => ConfigAbstractFactory::class,
            AppReleaseVersion::class                                => ConfigAbstractFactory::class,
            TurnstileVerifier::class                                => ConfigAbstractFactory::class,
            TurnstileRequestGuard::class                            => ConfigAbstractFactory::class,
            CsrfValidationMiddleware::class                         => ConfigAbstractFactory::class,
            HandleAuthorizationExceptionMiddleware::class           => ConfigAbstractFactory::class,
            HandleInertiaRequests::class                            => ConfigAbstractFactory::class,
            OptionalRouteAuthenticationMiddleware::class            => ConfigAbstractFactory::class,
            ResolveCurrentUserMiddleware::class                     => ConfigAbstractFactory::class,
            ResolveCurrentOrganizationMiddleware::class             => ConfigAbstractFactory::class,
            ResolveCurrentMembershipMiddleware::class               => ConfigAbstractFactory::class,
            ResolveOrganizationActorContextMiddleware::class        => ConfigAbstractFactory::class,
            RequireExistingOrganizationMiddleware::class            => ConfigAbstractFactory::class,
            ValidateMappedRequestMiddleware::class                  => ConfigAbstractFactory::class,
            RuntimeCorsMiddleware::class                            => ConfigAbstractFactory::class,
            OperationalProbeDispatchMiddleware::class               => ConfigAbstractFactory::class,
            RateLimitMiddleware::class                              => ConfigAbstractFactory::class,
            RequestSizeLimitMiddleware::class                       => ConfigAbstractFactory::class,
            RequireEmailVerificationMiddleware::class               => ConfigAbstractFactory::class,
            RequireUnbannedUserMiddleware::class                    => ConfigAbstractFactory::class,
            InertiaNotificationMessenger::class                     => ConfigAbstractFactory::class,
            TrustedProxyClientIpResolver::class                     => ConfigAbstractFactory::class,
            TrustedProxyRequestOriginResolver::class                => ConfigAbstractFactory::class,
            FileRateLimiter::class                                  => FileRateLimiterFactory::class,
            RateLimitCleanupCommand::class                          => ConfigAbstractFactory::class,
            InertiaSsrRenderer::class                               => ConfigAbstractFactory::class,
            InertiaSsrRootViewProvider::class                       => ConfigAbstractFactory::class,
            AppReleaseInertiaVersionProvider::class                 => ConfigAbstractFactory::class,
            AuthorizationExceptionResponseFactory::class            => ConfigAbstractFactory::class,
            MappingErrorResponder::class                            => ConfigAbstractFactory::class,
            CorsOptions::class                                      => CorsOptionsFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        ImplicitHeadRouterAdapter::class                       => [RadixRouter::class],
        ReadyHandler::class                                    => [
            ReadinessChecker::class,
        ],
        ReadinessChecker::class                                => [
            'config',
            RuntimeSettingKeysApiFacadeInterface::class,
            ArchiveStorageApiFacadeInterface::class,
        ],
        RootRedirectHandler::class                             => [
            OAuthApiFacadeInterface::class,
            BrandingConfig::class,
        ],
        SitemapHandler::class                                  => [
            BrandingConfig::class,
        ],
        AppReleaseVersion::class                               => [
            'config.packio',
        ],
        AppReleaseInertiaVersionProvider::class                => [
            AppReleaseVersion::class,
        ],
        TurnstileVerifier::class                               => [
            ClientInterface::class,
            LoggerInterface::class,
            'config.turnstile.secret_key',
            'config.turnstile.verify_url',
            'config.turnstile.hostname',
            'config.turnstile.validate_hostname',
        ],
        TurnstileRequestGuard::class                           => [
            TurnstileVerifierInterface::class,
            ClientIpResolverInterface::class,
            'config.turnstile',
            HoneypotValidatorInterface::class,
        ],
        CsrfValidationMiddleware::class                        => [
            CsrfTokenManager::class,
            RateLimiterInterface::class,
            LoggerInterface::class,
            TrustedProxyRequestOriginResolver::class,
        ],
        HandleAuthorizationExceptionMiddleware::class          => [
            AuthorizationExceptionResponseFactory::class,
        ],
        AuthorizationExceptionResponseFactory::class           => [
            InertiaNotificationMessenger::class,
            RequestPresentationPolicy::class,
        ],
        RequireEmailVerificationMiddleware::class              => [
            PendingEmailVerificationSession::class,
        ],
        RequireUnbannedUserMiddleware::class                   => [],
        OptionalRouteAuthenticationMiddleware::class           => [
            AuthenticationProfileProviderInterface::class,
        ],
        HandleInertiaRequests::class                           => [
            BrandingConfig::class,
            InitialThemeResolver::class,
            InitialListViewModeResolver::class,
            AppReleaseVersion::class,
            OrganizationApiFacadeInterface::class,
            ValidationRedirectState::class,
            CsrfTokenManager::class,
        ],
        ResolveCurrentUserMiddleware::class                    => [
            IdentityApiFacadeInterface::class,
            AuthManagerInterface::class,
        ],
        ResolveCurrentOrganizationMiddleware::class            => [
            OrganizationApiFacadeInterface::class,
        ],
        ResolveCurrentMembershipMiddleware::class              => [
            OrganizationApiFacadeInterface::class,
        ],
        ResolveOrganizationActorContextMiddleware::class       => [],
        RequireExistingOrganizationMiddleware::class           => [],
        ValidateMappedRequestMiddleware::class                 => [
            ValidatorInterface::class,
            MappedRequestValidationPlanResolver::class,
            ValidationResultDetailsNormalizer::class,
            ValidationFailureResponseDispatcher::class,
        ],
        RuntimeCorsMiddleware::class                           => [
            CorsMiddleware::class,
            ProjectSettingsApiFacadeInterface::class,
            CorsOptions::class,
        ],
        RequestSizeLimitMiddleware::class                      => [ResourceLimits::class],
        RateLimitMiddleware::class                             => [
            RateLimiterInterface::class,
            ClientIpResolverInterface::class,
            InertiaNotificationMessenger::class,
            'config.security.rate_limits',
        ],
        ApiAuthenticationAttemptRateLimitMiddleware::class     => [
            AuthenticationProfileProviderInterface::class,
            ApiAuthenticationAttemptLimiter::class,
        ],
        ApiAuthenticationAttemptLimiter::class                 => [
            RateLimiterInterface::class,
            ClientIpResolverInterface::class,
            'config.security.rate_limits',
        ],
        InertiaNotificationMessenger::class                    => [],
        ValidationRedirectState::class                         => [],
        ValidationErrorKeyNormalizer::class                    => [],
        ValidationFailureRequestPolicy::class                  => [],
        ValidationFailureResponseDispatcher::class             => [
            InertiaNotificationMessenger::class,
            ValidationRedirectState::class,
            ValidationFailureRequestPolicy::class,
            ValidationFailureResponseFactoryInterface::class,
            TrustedProxyRequestOriginResolver::class,
        ],
        ValidationFailureRoutePolicyResolver::class            => [],
        MappedRequestValidationPlanResolver::class             => [],
        JsonValidationFailureResponseFactory::class            => [],
        RequestAwareValidationFailureResponseFactory::class    => [
            JsonValidationFailureResponseFactory::class,
            ApiValidationFailureResponseFactoryInterface::class,
        ],
        ValidationResultDetailsNormalizer::class               => [
            ValidationErrorKeyNormalizer::class,
        ],
        MappingErrorDetailsNormalizer::class                   => [
            ValidationErrorKeyNormalizer::class,
        ],
        MappingErrorResponder::class                           => [
            ValidationFailureResponseFactoryInterface::class,
            ValidationFailureRequestPolicy::class,
            ValidationFailureRoutePolicyResolver::class,
            ValidationFailureResponseDispatcher::class,
            MappingErrorDetailsNormalizer::class,
            DefaultMappingErrorResponder::class,
        ],
        InertiaSsrRenderer::class                              => [
            ClientInterface::class,
            'config.inertia.ssr.enabled',
            'config.inertia.ssr.url',
            'config.inertia.ssr.timeout',
            'config.inertia.ssr.connect_timeout',
            LoggerInterface::class,
        ],
        InertiaSsrRootViewProvider::class                      => [
            TemplateRendererInterface::class,
            InertiaSsrRenderer::class,
        ],
        TrustedProxyClientIpResolver::class                    => [
            TrustedProxyMatcher::class,
            'config.security.client_ip.header',
        ],
        TrustedProxyMatcher::class                             => [
            'config.security.client_ip',
        ],
        TrustedProxyRequestOriginResolver::class               => [
            TrustedProxyMatcher::class,
        ],
        RateLimitCleanupCommand::class                         => [
            FileRateLimiter::class,
        ],
    ],
];
