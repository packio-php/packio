<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use Packio\Application\Error\InertiaErrorResponseGenerator;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Security\Authentication\CsrfAwareAuthManager;
use Packio\Application\Validation\RequestAwareValidationFailureResponseFactory;
use Packio\Application\Validation\ValidationFailureResponseDispatcher;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenStorage;
use Packio\Identity\Infrastructure\Authentication\DatabaseUserActorProvider;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use Packio\Identity\Infrastructure\Handler\OAuthSourceRedirectHandler;
use Packio\Identity\Infrastructure\Handler\RegisterHandler;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Packio\Rest\Infrastructure\Authentication\ApiAccessTokenTransport;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceProviderInstanceService;
use Packio\Source\Application\Provider\SourceProviderAvailabilityResolver;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Infrastructure\Handler\SourceResyncHandler;
use Packio\Worker\Application\JobProcessor;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenStorageProviderInterface;
use Sirix\Mezzio\Authentication\Exception\UnknownAuthenticationProfileException;
use Sirix\Mezzio\Authentication\Transport\CookieTokenTransport;

final class AuthenticationContainerIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function itResolvesTheWebProfileAsTheDefaultAuthenticationProfile(): void
    {
        $authenticationProfileProvider = $this->getService(AuthenticationProfileProviderInterface::class);
        self::assertInstanceOf(AuthenticationProfileProviderInterface::class, $authenticationProfileProvider);

        $authenticationProfile = $authenticationProfileProvider->getDefaultProfile();
        self::assertSame('web', $authenticationProfile->name());
        self::assertSame('session', $authenticationProfile->storageName());
        self::assertInstanceOf(CookieTokenTransport::class, $authenticationProfile->transport());
    }

    #[Test]
    public function itDecoratesTheInjectedAuthenticationManagerWithCsrfLifecycleHandling(): void
    {
        self::assertInstanceOf(CsrfAwareAuthManager::class, $this->getService(AuthManagerInterface::class));
    }

    #[Test]
    public function itResolvesTheApiProfileWithItsCustomTransportAndStorage(): void
    {
        $authenticationProfileProvider            = $this->getService(AuthenticationProfileProviderInterface::class);
        $authenticationProfile                    = $authenticationProfileProvider->get('api');

        self::assertSame('api', $authenticationProfile->name());
        self::assertSame('api_access_token', $authenticationProfile->storageName());
        self::assertInstanceOf(ApiAccessTokenTransport::class, $authenticationProfile->transport());
        self::assertInstanceOf(
            ApiAccessTokenStorage::class,
            $this->getService(TokenStorageProviderInterface::class)->getStorage($authenticationProfile->storageName()),
        );
        self::assertNotSame(
            $authenticationProfileProvider->getDefaultProfile()->optionalAuthenticateMiddleware(),
            $authenticationProfile->optionalAuthenticateMiddleware(),
        );
        self::assertNotSame(
            $authenticationProfileProvider->getDefaultProfile()->authenticateMiddleware(),
            $authenticationProfile->authenticateMiddleware(),
        );
    }

    #[Test]
    public function itFailsClosedForAnUnknownAuthenticationProfile(): void
    {
        $this->expectException(UnknownAuthenticationProfileException::class);

        $this->getService(AuthenticationProfileProviderInterface::class)->get('missing-profile');
    }

    /**
     * @param class-string $service
     */
    #[Test]
    #[DataProvider('servicesWithRequiredDependencies')]
    public function itBuildsServicesWhoseDependenciesAreRequired(string $service): void
    {
        self::assertInstanceOf($service, $this->getService($service));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function servicesWithRequiredDependencies(): iterable
    {
        foreach ([
            SourceProviderInstanceService::class,
            SourceCommandService::class,
            SourceOAuthService::class,
            SourceOAuthTokenResolver::class,
            WebhookAutoRegistrationService::class,
            SourceProviderAvailabilityResolver::class,
            OAuthSessionContext::class,
            OAuthProviderAvailabilityResolver::class,
            OAuthSourceRedirectHandler::class,
            LoginHandler::class,
            RegisterHandler::class,
            JobProcessor::class,
            InertiaErrorResponseGenerator::class,
            RequestAwareValidationFailureResponseFactory::class,
            ValidationFailureResponseDispatcher::class,
            ValidateMappedRequestMiddleware::class,
            DatabaseUserActorProvider::class,
            SourceResyncHandler::class,
        ] as $service) {
            yield $service => [$service];
        }
    }
}
