<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Middleware;

use LogicException;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Packio\Identity\Api\Authentication\ApiAccessTokenContract;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Shared\Api\Authentication\CurrentUserActorInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

use function is_string;

final readonly class ApiAuthenticationContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationProfileProviderInterface $authenticationProfileProvider,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
        private ApiAuthenticationAttemptLimiter $apiAuthenticationAttemptLimiter,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $request->getAttribute(AuthenticationAttributes::Context->value);
        if (! $context instanceof AuthContextInterface || $context->guest()) {
            $transportToken = $this->apiTransportToken($request);
            if (null !== $transportToken) {
                $this->apiAuthenticationAttemptLimiter->recordFailedCredential($request);
            }

            return $this->apiErrorResponseFactory->unauthorized(
                null === $transportToken ? 'Authentication required.' : 'Invalid API token.',
            );
        }

        $token = $context->token();
        $actor = $context->actor();
        if (! $token instanceof TokenInterface
            || ApiAccessTokenContract::STORAGE !== $token->getStorage()
            || ! $actor instanceof CurrentUserActorInterface
        ) {
            throw new LogicException('API authentication profile produced an invalid authenticated context.');
        }

        $payload       = $token->getPayload();
        $payloadToken  = $payload[ApiAccessTokenContract::PAYLOAD_ACCESS_TOKEN] ?? null;
        $payloadUserId = $payload['user_id'] ?? null;
        $user          = $actor->currentUser();
        if (! $payloadToken instanceof ApiAccessTokenDto
            || ! is_string($payloadUserId)
            || ! $user instanceof UserDto
            || $payloadToken->id !== $token->getId()
            || $payloadToken->userId !== $payloadUserId
            || $payloadToken->userId !== $user->id
        ) {
            throw new LogicException('API authentication profile token payload does not match its actor.');
        }

        if ($user->isBanned) {
            return $this->apiErrorResponseFactory->accountBanned($user->banReason, $user->bannedAt);
        }

        if (! $user->isEmailVerified) {
            return $this->apiErrorResponseFactory->emailVerificationRequired();
        }

        $request            = RequestContextAttributes::withCurrentUser($request, $user)
            ->withAttribute(ApiRequestAttributes::ACCESS_TOKEN, $payloadToken)
        ;

        return $handler->handle($request);
    }

    private function apiTransportToken(ServerRequestInterface $serverRequest): ?string
    {
        return $this->authenticationProfileProvider
            ->get(AuthenticationRouteOptions::API_PROFILE)
            ->transport()
            ->fetch($serverRequest)
        ;
    }
}
