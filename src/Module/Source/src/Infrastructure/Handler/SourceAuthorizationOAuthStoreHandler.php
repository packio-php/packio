<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Exception\SourceOAuthAuthorizationException;
use Packio\Source\Infrastructure\Request\ProviderAuthorizationOAuthRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/sources/authorizations/oauth',
    name: 'sources.authorizations.oauth_store',
)]
#[MapRequest(body: ProviderAuthorizationOAuthRequest::class)]
#[ValidateMappedRequest(ProviderAuthorizationOAuthRequest::class)]
#[ValidationFailed(
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    jsonStatus: 422,
)]
final readonly class SourceAuthorizationOAuthStoreHandler implements RequestHandlerInterface
{
    public function __construct(private SourceApiFacadeInterface $sourceApiFacade, private ?LoggerInterface $logger = null) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);
        $context      = RequestContextAttributes::currentOrganizationActorContext($request);
        if (! $organization instanceof OrganizationDto || ! $context instanceof ActorContext) {
            return new JsonResponse(
                $this->responseWithToast('error', 'Organization not found'),
                404,
            );
        }

        /** @var ProviderAuthorizationOAuthRequest $form */
        $form                 = $request->getAttribute(ProviderAuthorizationOAuthRequest::class);
        $provider             = $form->provider;
        $oauthAccountId       = $form->oauthAccountId;
        $sourceOAuthAccountId = $form->sourceOauthAccountId;

        if ('' === $oauthAccountId && '' === $sourceOAuthAccountId) {
            return new JsonResponse(
                $this->responseWithToast('error', 'Source OAuth account ID is required.'),
                422,
            );
        }

        if ('' !== $oauthAccountId && '' === $sourceOAuthAccountId) {
            return new JsonResponse(
                $this->responseWithToast(
                    'error',
                    'Source authorization requires a source OAuth connection. Please reconnect the provider.',
                ),
                422,
            );
        }

        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            return new JsonResponse(
                $this->responseWithToast('error', 'Authentication required.'),
                401,
            );
        }

        try {
            $authorization = $this->sourceApiFacade->authorizeProviderFromSourceOAuth(
                $context,
                $currentUser->id,
                $provider,
                $sourceOAuthAccountId,
            );

            return new JsonResponse([
                'authorization' => $this->authorizationPayload($authorization),
                'toast'         => $this->toastPayload(
                    'success',
                    'Provider authorization created. You can now select repositories.',
                ),
            ]);
        } catch (SourceOAuthAuthorizationException $exception) {
            return new JsonResponse(
                $this->responseWithToast('error', 'Provider authorization failed.'),
                SourceOAuthAuthorizationStatusMapper::forSourceOAuthException($exception),
            );
        } catch (Throwable $exception) {
            $this->logger?->error('Provider authorization failed.', SafeExceptionContext::forRequest($exception, $request));

            return new JsonResponse(
                $this->responseWithToast('error', 'Provider authorization failed.'),
                500,
            );
        }
    }

    /** @return array<string, mixed> */
    private function authorizationPayload(ProviderAuthorizationDto $providerAuthorizationDto): array
    {
        return [
            'id'                      => $providerAuthorizationDto->id,
            'provider'                => $providerAuthorizationDto->provider,
            'auth_strategy'           => $providerAuthorizationDto->authStrategy,
            'status'                  => $providerAuthorizationDto->status,
            'account_name'            => $providerAuthorizationDto->accountName,
            'configured'              => $providerAuthorizationDto->configured,
            'masked_value'            => $providerAuthorizationDto->maskedValue,
            'source_oauth_account_id' => $providerAuthorizationDto->sourceOAuthAccountId,
            'provider_instance_id'    => $providerAuthorizationDto->providerInstanceId,
            'auth_health'             => $providerAuthorizationDto->authHealth,
            'auth_failure_reason'     => $providerAuthorizationDto->authFailureReason,
            'last_token_refresh_at'   => $providerAuthorizationDto->lastTokenRefreshAt,
        ];
    }

    /** @return array{type: string, message: string} */
    private function toastPayload(string $type, string $message): array
    {
        return [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /** @return array{error: string, toast: array{type: string, message: string}} */
    private function responseWithToast(string $type, string $message): array
    {
        return [
            'error' => $message,
            'toast' => $this->toastPayload($type, $message),
        ];
    }
}
