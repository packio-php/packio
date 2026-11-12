<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Mail\MailerInterface;
use Common\Provider\SourceProviderMetadataCatalog;
use Common\Security\SecretEncryptorInterface;
use Cycle\ORM\ORMInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Application\Command\IdentityCredentialsInterface;
use Packio\Identity\Application\Command\OAuth\BrowserOAuthWorkflow;
use Packio\Identity\Application\Command\OAuth\BrowserOAuthWorkflowInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationInterface;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationWorkflow;
use Packio\Identity\Application\Command\OAuth\OAuthProviderInterface;
use Packio\Identity\Application\Command\PasswordResetService;
use Packio\Identity\Application\Command\PasswordResetWorkflow;
use Packio\Identity\Application\Command\RegistrationWorkflow;
use Packio\Identity\Application\Command\RegistryTokenCommandService;
use Packio\Identity\Application\Command\SignInWorkflow;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Application\Listener\SendEmailVerificationCodeListener;
use Packio\Identity\Application\Listener\SendPasswordResetLinkListener;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Packio\Identity\Application\Port\AuthMailQueuePort;
use Packio\Identity\Application\Port\RegistrationPolicyPort;
use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Application\Validator\PasswordValidator;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Adapter\RegistrationPolicyAdapter;
use Packio\Identity\Infrastructure\Adapter\Worker\AuthMailJobHandler;
use Packio\Identity\Infrastructure\Adapter\Worker\AuthMailQueueAdapter;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenStorage;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenUsageRecordingActorProvider;
use Packio\Identity\Infrastructure\Authentication\DatabaseUserActorProvider;
use Packio\Identity\Infrastructure\Cli\EmailVerificationCleanupCommand;
use Packio\Identity\Infrastructure\Cli\MakeUserAdminCommand;
use Packio\Identity\Infrastructure\Cli\PasswordResetCleanupCommand;
use Packio\Identity\Infrastructure\Facade\IdentityApiFacade;
use Packio\Identity\Infrastructure\Facade\OAuthApiFacade;
use Packio\Identity\Infrastructure\Handler\BannedAccountPageHandler;
use Packio\Identity\Infrastructure\Handler\ChangePasswordHandler;
use Packio\Identity\Infrastructure\Handler\DisconnectOAuthAccountHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationResendHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationSendHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationVerifyHandler;
use Packio\Identity\Infrastructure\Handler\ForgotPasswordHandler;
use Packio\Identity\Infrastructure\Handler\ForgotPasswordPageHandler;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use Packio\Identity\Infrastructure\Handler\LoginPageHandler;
use Packio\Identity\Infrastructure\Handler\LogoutHandler;
use Packio\Identity\Infrastructure\Handler\OAuthCallbackHandler;
use Packio\Identity\Infrastructure\Handler\OAuthEmailHandler;
use Packio\Identity\Infrastructure\Handler\OAuthEmailSubmitHandler;
use Packio\Identity\Infrastructure\Handler\OAuthOutcomePresenter;
use Packio\Identity\Infrastructure\Handler\OAuthRedirectHandler;
use Packio\Identity\Infrastructure\Handler\OAuthSourceCallbackHandler;
use Packio\Identity\Infrastructure\Handler\OAuthSourceRedirectHandler;
use Packio\Identity\Infrastructure\Handler\ProfileApiTokenDeleteHandler;
use Packio\Identity\Infrastructure\Handler\ProfileApiTokenIssueHandler;
use Packio\Identity\Infrastructure\Handler\ProfileApiTokenRevokeHandler;
use Packio\Identity\Infrastructure\Handler\ProfileHandler;
use Packio\Identity\Infrastructure\Handler\RegisterHandler;
use Packio\Identity\Infrastructure\Handler\RegisterPageHandler;
use Packio\Identity\Infrastructure\Handler\RegistryTokenIssueHandler;
use Packio\Identity\Infrastructure\Handler\RegistryTokenRevokeHandler;
use Packio\Identity\Infrastructure\Handler\RegistryTokensHandler;
use Packio\Identity\Infrastructure\Handler\ResetPasswordHandler;
use Packio\Identity\Infrastructure\Handler\ResetPasswordPageHandler;
use Packio\Identity\Infrastructure\Handler\SourceOAuthPopupRenderer;
use Packio\Identity\Infrastructure\Mail\AuthEmailTemplateRenderer;
use Packio\Identity\Infrastructure\OAuth\Client\BitbucketOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\CodebergOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitHubOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitLabOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthHttpClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthScopeResolver;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use Packio\Identity\Infrastructure\OAuth\OAuthAccountLinker;
use Packio\Identity\Infrastructure\OAuth\OAuthCallbackTransaction;
use Packio\Identity\Infrastructure\OAuth\OAuthClientConfigResolver;
use Packio\Identity\Infrastructure\OAuth\OAuthClientFactory;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderGateway;
use Packio\Identity\Infrastructure\OAuth\OAuthRegistrationPolicy;
use Packio\Identity\Infrastructure\OAuth\OAuthUserResolver;
use Packio\Identity\Infrastructure\OAuth\SourceOAuthCallbackWorkflow;
use Packio\Identity\Infrastructure\Page\ProfileViewModelFactory;
use Packio\Identity\Infrastructure\Persistence\Cycle\ApiAccessTokenRepository;
use Packio\Identity\Infrastructure\Persistence\Cycle\EmailVerificationCodeRepository;
use Packio\Identity\Infrastructure\Persistence\Cycle\PasswordResetTokenRepository;
use Packio\Identity\Infrastructure\Persistence\Cycle\RegistryAccessTokenRepository;
use Packio\Identity\Infrastructure\Persistence\Cycle\UserOAuthAccountRepository;
use Packio\Identity\Infrastructure\Persistence\Cycle\UserRepository;
use Packio\Identity\Infrastructure\Support\OAuthCallbackStateValidator;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Sirix\Cycle\Factory\RepositoryFactory;
use Sirix\Mezzio\Authentication\Contract\AuthActorProviderInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

return [
    'dependencies'               => [
        'aliases'    => [
            AuthMailQueuePort::class                             => AuthMailQueueAdapter::class,
            AuthEmailTemplateRendererInterface::class            => AuthEmailTemplateRenderer::class,
            RegistrationPolicyPort::class                        => RegistrationPolicyAdapter::class,
            IdentityCredentialsInterface::class                  => IdentityQueryService::class,
            OAuthContinuationInterface::class                    => OAuthContinuationWorkflow::class,
            BrowserOAuthWorkflowInterface::class                 => BrowserOAuthWorkflow::class,
            UserRepositoryInterface::class                       => UserRepository::class,
            ApiAccessTokenRepositoryInterface::class             => ApiAccessTokenRepository::class,
            RegistryAccessTokenRepositoryInterface::class        => RegistryAccessTokenRepository::class,
            UserOAuthAccountRepositoryInterface::class           => UserOAuthAccountRepository::class,
            EmailVerificationCodeRepositoryInterface::class      => EmailVerificationCodeRepository::class,
            PasswordResetTokenRepositoryInterface::class         => PasswordResetTokenRepository::class,
            IdentityApiFacadeInterface::class                    => IdentityApiFacade::class,
            OAuthApiFacadeInterface::class                       => OAuthApiFacade::class,
            OAuthProviderInterface::class                        => OAuthProviderGateway::class,
            AuthActorProviderInterface::class                    => ApiAccessTokenUsageRecordingActorProvider::class,
        ],
        'invokables' => [
            ProfileViewModelFactory::class          => ProfileViewModelFactory::class,
            BannedAccountPageHandler::class         => BannedAccountPageHandler::class,
            SourceOAuthPopupRenderer::class         => SourceOAuthPopupRenderer::class,
            PasswordValidator::class                => PasswordValidator::class,
            OAuthCallbackStateValidator::class      => OAuthCallbackStateValidator::class,
            OAuthScopeResolver::class               => OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class         => OAuthTokenResponseMapper::class,
        ],
        'factories'  => [
            AuthMailQueueAdapter::class                             => ConfigAbstractFactory::class,
            AuthEmailTemplateRenderer::class                        => ConfigAbstractFactory::class,
            AuthMailJobHandler::class                               => ConfigAbstractFactory::class,
            PasswordResetWorkflow::class                            => ConfigAbstractFactory::class,
            RegistrationWorkflow::class                             => ConfigAbstractFactory::class,
            RegistrationPolicyAdapter::class                        => ConfigAbstractFactory::class,
            SignInWorkflow::class                                   => ConfigAbstractFactory::class,
            SourceOAuthCallbackWorkflow::class                      => ConfigAbstractFactory::class,
            OAuthContinuationWorkflow::class                        => ConfigAbstractFactory::class,
            VerificationDispatch::class                             => ConfigAbstractFactory::class,
            BrowserOAuthWorkflow::class                             => ConfigAbstractFactory::class,
            OAuthOutcomePresenter::class                            => ConfigAbstractFactory::class,
            // --- Repositories ---
            ApiAccessTokenRepository::class                         => RepositoryFactory::class,
            UserRepository::class                                   => RepositoryFactory::class,
            RegistryAccessTokenRepository::class                    => RepositoryFactory::class,
            UserOAuthAccountRepository::class                       => RepositoryFactory::class,
            EmailVerificationCodeRepository::class                  => RepositoryFactory::class,
            PasswordResetTokenRepository::class                     => RepositoryFactory::class,

            // --- Domain services ---
            ApiAccessTokenHasher::class                             => ConfigAbstractFactory::class,
            ApiAccessTokenCommandService::class                     => ConfigAbstractFactory::class,
            ApiAccessTokenQueryService::class                       => ConfigAbstractFactory::class,
            ApiAccessTokenStorage::class                            => ConfigAbstractFactory::class,
            ApiAccessTokenUsageRecordingActorProvider::class        => ConfigAbstractFactory::class,
            RegistryAccessTokenSecrets::class                       => ConfigAbstractFactory::class,
            IdentityQueryService::class                             => ConfigAbstractFactory::class,
            IdentityCommandService::class                           => ConfigAbstractFactory::class,
            RegistryTokenCommandService::class                      => ConfigAbstractFactory::class,
            EmailVerificationService::class                         => ConfigAbstractFactory::class,
            PasswordResetService::class                             => ConfigAbstractFactory::class,

            // --- OAuth ---
            OAuthClientConfigResolver::class                        => ConfigAbstractFactory::class,
            OAuthHttpClient::class                                  => ConfigAbstractFactory::class,
            OAuthClientFactory::class                               => ConfigAbstractFactory::class,
            GitHubOAuthClient::class                                => ConfigAbstractFactory::class,
            GitLabOAuthClient::class                                => ConfigAbstractFactory::class,
            BitbucketOAuthClient::class                             => ConfigAbstractFactory::class,
            CodebergOAuthClient::class                              => ConfigAbstractFactory::class,
            OAuthProviderAvailabilityResolver::class                => ConfigAbstractFactory::class,
            OAuthSessionContext::class                              => ConfigAbstractFactory::class,

            // --- Adapters ---
            OAuthProviderGateway::class                             => ConfigAbstractFactory::class,
            DatabaseUserActorProvider::class                        => ConfigAbstractFactory::class,

            // --- Facades ---
            IdentityApiFacade::class                                => ConfigAbstractFactory::class,
            OAuthApiFacade::class                                   => ConfigAbstractFactory::class,

            // --- OAuth Callback ---
            OAuthRegistrationPolicy::class                          => ConfigAbstractFactory::class,
            OAuthUserResolver::class                                => ConfigAbstractFactory::class,
            OAuthAccountLinker::class                               => ConfigAbstractFactory::class,
            OAuthCallbackUseCase::class                             => ConfigAbstractFactory::class,
            OAuthCallbackTransaction::class                         => ConfigAbstractFactory::class,

            // --- Handlers ---
            LoginHandler::class                                     => ConfigAbstractFactory::class,
            RegisterHandler::class                                  => ConfigAbstractFactory::class,
            LogoutHandler::class                                    => ConfigAbstractFactory::class,
            EmailVerificationHandler::class                         => ConfigAbstractFactory::class,
            EmailVerificationVerifyHandler::class                   => ConfigAbstractFactory::class,
            EmailVerificationSendHandler::class                     => ConfigAbstractFactory::class,
            EmailVerificationResendHandler::class                   => ConfigAbstractFactory::class,
            ForgotPasswordHandler::class                            => ConfigAbstractFactory::class,
            ResetPasswordHandler::class                             => ConfigAbstractFactory::class,
            OAuthRedirectHandler::class                             => ConfigAbstractFactory::class,
            OAuthCallbackHandler::class                             => ConfigAbstractFactory::class,
            OAuthEmailHandler::class                                => ConfigAbstractFactory::class,
            OAuthEmailSubmitHandler::class                          => ConfigAbstractFactory::class,
            OAuthSourceRedirectHandler::class                       => ConfigAbstractFactory::class,
            OAuthSourceCallbackHandler::class                       => ConfigAbstractFactory::class,
            RegistryTokenIssueHandler::class                        => ConfigAbstractFactory::class,
            RegistryTokenRevokeHandler::class                       => ConfigAbstractFactory::class,
            RegistryTokensHandler::class                            => ConfigAbstractFactory::class,
            LoginPageHandler::class                                 => ConfigAbstractFactory::class,
            RegisterPageHandler::class                              => ConfigAbstractFactory::class,
            ForgotPasswordPageHandler::class                        => ConfigAbstractFactory::class,
            ResetPasswordPageHandler::class                         => ConfigAbstractFactory::class,
            ProfileHandler::class                                   => ConfigAbstractFactory::class,
            ProfileApiTokenDeleteHandler::class                     => ConfigAbstractFactory::class,
            ProfileApiTokenIssueHandler::class                      => ConfigAbstractFactory::class,
            ProfileApiTokenRevokeHandler::class                     => ConfigAbstractFactory::class,
            ChangePasswordHandler::class                            => ConfigAbstractFactory::class,
            DisconnectOAuthAccountHandler::class                    => ConfigAbstractFactory::class,

            // --- Listeners ---
            SendEmailVerificationCodeListener::class                => ConfigAbstractFactory::class,
            SendPasswordResetLinkListener::class                    => ConfigAbstractFactory::class,

            // --- CLI Commands ---
            EmailVerificationCleanupCommand::class                  => ConfigAbstractFactory::class,
            MakeUserAdminCommand::class                             => ConfigAbstractFactory::class,
            PasswordResetCleanupCommand::class                      => ConfigAbstractFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        AuthMailQueueAdapter::class                             => [JobApiFacadeInterface::class, SecretEncryptorInterface::class],
        AuthEmailTemplateRenderer::class                        => [BrandingConfig::class],
        AuthMailJobHandler::class                               => [SecretEncryptorInterface::class, EmailVerificationService::class, PasswordResetService::class],
        PasswordResetWorkflow::class                            => [PasswordResetService::class, PasswordResetTokenRepositoryInterface::class, UserRepositoryInterface::class, PasswordValidator::class],
        RegistrationWorkflow::class                             => [RegistrationPolicyPort::class, IdentityCommandService::class, VerificationDispatch::class],
        RegistrationPolicyAdapter::class                        => [ProjectSettingsApiFacadeInterface::class, OrganizationApiFacadeInterface::class],
        SignInWorkflow::class                                   => [IdentityCredentialsInterface::class, VerificationDispatch::class],
        SourceOAuthCallbackWorkflow::class                      => [SourceApiFacadeInterface::class],
        OAuthContinuationWorkflow::class                        => [OAuthCallbackUseCase::class, UserRepositoryInterface::class, EmailVerificationService::class, VerificationDispatch::class, OAuthCallbackTransaction::class],
        VerificationDispatch::class                             => [EventDispatcherInterface::class, LoggerInterface::class],
        BrowserOAuthWorkflow::class                             => [OAuthProviderInterface::class, OAuthCallbackUseCase::class],
        OAuthOutcomePresenter::class                            => [AuthManagerInterface::class, OAuthSessionContext::class, PendingEmailVerificationSession::class, InertiaNotificationMessenger::class, IdentityQueryService::class],
        // --- Domain services ---
        ApiAccessTokenHasher::class                             => [
            'config.security.secret_encryption_key',
            'config.packio.production_mode',
        ],
        ApiAccessTokenCommandService::class                     => [
            ApiAccessTokenRepositoryInterface::class,
            ApiAccessTokenHasher::class,
            ObjectMapperInterface::class,
            UserRepositoryInterface::class,
            'config.api.tokens',
        ],
        ApiAccessTokenQueryService::class                       => [
            ApiAccessTokenRepositoryInterface::class,
            ApiAccessTokenHasher::class,
            ObjectMapperInterface::class,
        ],
        ApiAccessTokenStorage::class                            => [
            ApiAccessTokenQueryService::class,
            ObjectMapperInterface::class,
        ],
        ApiAccessTokenUsageRecordingActorProvider::class        => [
            DatabaseUserActorProvider::class,
            ApiAccessTokenQueryService::class,
        ],
        RegistryAccessTokenSecrets::class                       => [
            SecretEncryptorInterface::class,
        ],
        IdentityQueryService::class                             => [
            UserRepositoryInterface::class,
            RegistryAccessTokenRepositoryInterface::class,
            UserOAuthAccountRepositoryInterface::class,
            RegistryAccessTokenSecrets::class,
        ],
        IdentityCommandService::class                           => [
            UserRepositoryInterface::class,
            RegistryTokenCommandService::class,
            UserOAuthAccountRepositoryInterface::class,
            PasswordValidator::class,
        ],
        RegistryTokenCommandService::class                      => [
            RegistryAccessTokenRepositoryInterface::class,
            BrandingConfig::class,
            RegistryAccessTokenSecrets::class,
        ],
        EmailVerificationService::class                         => [
            EmailVerificationCodeRepositoryInterface::class,
            UserRepositoryInterface::class,
            MailerInterface::class,
            AuthEmailTemplateRendererInterface::class,
        ],
        PasswordResetService::class                             => [
            PasswordResetTokenRepositoryInterface::class,
            UserRepositoryInterface::class,
            EventDispatcherInterface::class,
            'config.security.password_reset',
        ],
        // --- OAuth ---
        OAuthClientFactory::class                               => [
            GitHubOAuthClient::class,
            GitLabOAuthClient::class,
            BitbucketOAuthClient::class,
            CodebergOAuthClient::class,
            BrandingConfig::class,
            OAuthClientConfigResolver::class,
            OAuthHttpClient::class,
            OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class,
        ],
        OAuthClientConfigResolver::class                        => [
            'config.oauth',
        ],
        OAuthHttpClient::class                                  => [
            UpstreamHttpClient::class,
        ],
        GitHubOAuthClient::class                                => [
            BrandingConfig::class,
            'config.oauth.github',
            OAuthHttpClient::class,
            OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class,
        ],
        GitLabOAuthClient::class                                => [
            BrandingConfig::class,
            'config.oauth.gitlab',
            OAuthHttpClient::class,
            OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class,
        ],
        BitbucketOAuthClient::class                             => [
            BrandingConfig::class,
            'config.oauth.bitbucket',
            OAuthHttpClient::class,
            OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class,
        ],
        CodebergOAuthClient::class                              => [
            BrandingConfig::class,
            'config.oauth.codeberg',
            OAuthHttpClient::class,
            OAuthScopeResolver::class,
            OAuthTokenResponseMapper::class,
        ],
        OAuthProviderAvailabilityResolver::class                => [
            'config.oauth',
            SourceProviderMetadataCatalog::class,
            ProjectSettingsApiFacadeInterface::class,
        ],
        OAuthSessionContext::class                              => [
            SecretEncryptorInterface::class,
        ],

        // --- Facades ---
        IdentityApiFacade::class                                => [
            IdentityCommandService::class,
            EmailVerificationService::class,
            PasswordResetService::class,
            ApiAccessTokenCommandService::class,
            ApiAccessTokenQueryService::class,
            IdentityQueryService::class,
            UserRepositoryInterface::class,
            EmailVerificationCodeRepositoryInterface::class,
            PasswordResetTokenRepositoryInterface::class,
            EventDispatcherInterface::class,
            ObjectMapperInterface::class,
            AuthMailQueuePort::class,
            AuthManagerInterface::class,
        ],
        OAuthApiFacade::class                                   => [
            OAuthClientFactory::class,
            OAuthProviderAvailabilityResolver::class,
        ],
        OAuthCallbackUseCase::class                             => [
            UserRepositoryInterface::class,
            UserOAuthAccountRepositoryInterface::class,
            OAuthRegistrationPolicy::class,
            OAuthUserResolver::class,
            OAuthAccountLinker::class,
            OAuthCallbackTransaction::class,
        ],
        OAuthCallbackTransaction::class                         => [ORMInterface::class],
        OAuthRegistrationPolicy::class                          => [
            UserRepositoryInterface::class,
            RegistrationPolicyPort::class,
        ],
        OAuthUserResolver::class                                => [
            UserRepositoryInterface::class,
            IdentityCommandService::class,
        ],
        OAuthAccountLinker::class                               => [
            UserOAuthAccountRepositoryInterface::class,
        ],
        OAuthProviderGateway::class                             => [
            OAuthClientFactory::class,
        ],
        DatabaseUserActorProvider::class                        => [
            UserRepositoryInterface::class,
            ObjectMapperInterface::class,
        ],

        // --- Handlers ---
        LoginHandler::class                                     => [
            SignInWorkflow::class,
            AuthManagerInterface::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            IdentityQueryService::class,
            PendingEmailVerificationSession::class,
            ValidationRedirectState::class,
            OAuthSessionContext::class,
        ],
        RegisterHandler::class                                  => [
            RegistrationWorkflow::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            ValidationRedirectState::class,
            PendingEmailVerificationSession::class,
            OAuthSessionContext::class,
        ],
        LogoutHandler::class                                    => [
            AuthManagerInterface::class,
        ],
        EmailVerificationHandler::class                         => [
            IdentityApiFacadeInterface::class,
            HoneypotFieldNameGenerator::class,
            BrandingConfig::class,
            TurnstileRequestGuard::class,
            PendingEmailVerificationSession::class,
        ],
        EmailVerificationVerifyHandler::class                   => [
            OAuthContinuationInterface::class,
            OAuthOutcomePresenter::class,
            InertiaNotificationMessenger::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            OAuthSessionContext::class,
            PendingEmailVerificationSession::class,
            ValidationRedirectState::class,
        ],
        EmailVerificationSendHandler::class                     => [
            IdentityApiFacadeInterface::class,
            PendingEmailVerificationSession::class,
        ],
        EmailVerificationResendHandler::class                   => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            TurnstileRequestGuard::class,
            PendingEmailVerificationSession::class,
            ValidationRedirectState::class,
        ],
        ForgotPasswordHandler::class                            => [
            IdentityApiFacadeInterface::class,
            ClientIpResolverInterface::class,
            InertiaNotificationMessenger::class,
            ValidationRedirectState::class,
            TurnstileRequestGuard::class,
        ],
        ResetPasswordHandler::class                             => [
            PasswordResetWorkflow::class,
            InertiaNotificationMessenger::class,
            ValidationRedirectState::class,
            TurnstileRequestGuard::class,
            HoneypotFieldNameGenerator::class,
        ],
        OAuthRedirectHandler::class                             => [
            OAuthApiFacadeInterface::class,
            OAuthSessionContext::class,
            OAuthProviderAvailabilityResolver::class,
            InertiaNotificationMessenger::class,
            SafeRedirectTarget::class,
        ],
        OAuthCallbackHandler::class                             => [
            BrowserOAuthWorkflowInterface::class,
            OAuthOutcomePresenter::class,
            OAuthSessionContext::class,
            OAuthCallbackStateValidator::class,
            OAuthProviderAvailabilityResolver::class,
            InertiaNotificationMessenger::class,
            SafeRedirectTarget::class,
        ],
        OAuthEmailHandler::class                                => [
            OAuthSessionContext::class,
            InertiaNotificationMessenger::class,
            HoneypotFieldNameGenerator::class,
            BrandingConfig::class,
            TurnstileRequestGuard::class,
        ],
        OAuthEmailSubmitHandler::class                          => [
            OAuthSessionContext::class,
            OAuthContinuationInterface::class,
            OAuthOutcomePresenter::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            InertiaNotificationMessenger::class,
            PendingEmailVerificationSession::class,
            ValidationRedirectState::class,
        ],
        OAuthSourceRedirectHandler::class                       => [
            OAuthApiFacadeInterface::class,
            OAuthSessionContext::class,
            OAuthProviderAvailabilityResolver::class,
            SourceApiFacadeInterface::class,
        ],
        OAuthSourceCallbackHandler::class                       => [
            OAuthSessionContext::class,
            OAuthCallbackStateValidator::class,
            OAuthProviderAvailabilityResolver::class,
            SourceOAuthCallbackWorkflow::class,
            InertiaNotificationMessenger::class,
            SourceOAuthPopupRenderer::class,
        ],
        RegistryTokenIssueHandler::class                        => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        RegistryTokenRevokeHandler::class                       => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        RegistryTokensHandler::class                            => [
            IdentityApiFacadeInterface::class,
            OrganizationPolicy::class,
        ],

        LoginPageHandler::class                                 => [
            OAuthProviderAvailabilityResolver::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            HoneypotFieldNameGenerator::class,
            RegistrationPolicyPort::class,
        ],
        RegisterPageHandler::class                              => [
            OAuthProviderAvailabilityResolver::class,
            SafeRedirectTarget::class,
            TurnstileRequestGuard::class,
            HoneypotFieldNameGenerator::class,
            RegistrationPolicyPort::class,
        ],
        ForgotPasswordPageHandler::class                        => [
            TurnstileRequestGuard::class,
            HoneypotFieldNameGenerator::class,
        ],
        ResetPasswordPageHandler::class                         => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
            TurnstileRequestGuard::class,
            HoneypotFieldNameGenerator::class,
        ],
        ProfileHandler::class                                   => [
            IdentityQueryService::class,
            IdentityApiFacadeInterface::class,
            ProfileViewModelFactory::class,
        ],
        ProfileApiTokenDeleteHandler::class                     => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        ProfileApiTokenIssueHandler::class                      => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        ProfileApiTokenRevokeHandler::class                     => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],
        ChangePasswordHandler::class                            => [
            IdentityApiFacadeInterface::class,
            IdentityQueryService::class,
            InertiaNotificationMessenger::class,
            ProfileViewModelFactory::class,
            AuthManagerInterface::class,
        ],
        DisconnectOAuthAccountHandler::class                    => [
            IdentityApiFacadeInterface::class,
            InertiaNotificationMessenger::class,
        ],

        // --- Listeners ---
        SendEmailVerificationCodeListener::class                => [AuthMailQueuePort::class],
        SendPasswordResetLinkListener::class                    => [
            MailerInterface::class,
            AuthEmailTemplateRendererInterface::class,
            BrandingConfig::class,
            'config.security.password_reset',
            LoggerInterface::class,
        ],

        // --- CLI Commands ---
        EmailVerificationCleanupCommand::class                  => [
            IdentityApiFacadeInterface::class,
        ],
        MakeUserAdminCommand::class                             => [
            UserRepositoryInterface::class,
        ],
        PasswordResetCleanupCommand::class                      => [
            IdentityApiFacadeInterface::class,
        ],
    ],
    'worker'                     => [
        'job_handlers' => [
            AuthMailQueueAdapter::TYPE => AuthMailJobHandler::class,
        ],
    ],
];
