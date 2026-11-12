<?php

declare(strict_types=1);

namespace Packio\Application\Security\Authentication;

use LogicException;
use Mezzio\Session\SessionInterface;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

final readonly class CsrfAwareAuthManager implements AuthManagerInterface
{
    public function __construct(
        private AuthManagerInterface $authManager,
        private CsrfTokenManager $csrfTokenManager,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function login(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        array $payload,
        ?int $expiresAt = null,
    ): ResponseInterface {
        $session  = $this->session($serverRequest);
        $response = $this->authManager->login($serverRequest, $response, $payload, $expiresAt);

        $this->csrfTokenManager->rotate($session);

        return $response;
    }

    public function logout(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        $session  = $this->session($serverRequest);
        $response = $this->authManager->logout($serverRequest, $response);

        $this->csrfTokenManager->clear($session);

        return $response;
    }

    public function token(ServerRequestInterface $serverRequest): ?TokenInterface
    {
        return $this->authManager->token($serverRequest);
    }

    public function actor(ServerRequestInterface $serverRequest): ?ActorInterface
    {
        return $this->authManager->actor($serverRequest);
    }

    public function check(ServerRequestInterface $serverRequest): bool
    {
        return $this->authManager->check($serverRequest);
    }

    public function guest(ServerRequestInterface $serverRequest): bool
    {
        return $this->authManager->guest($serverRequest);
    }

    public function context(ServerRequestInterface $serverRequest): AuthContextInterface
    {
        return $this->authManager->context($serverRequest);
    }

    private function session(ServerRequestInterface $serverRequest): SessionInterface
    {
        $session = $serverRequest->getAttribute(SessionInterface::class);
        if (! $session instanceof SessionInterface) {
            throw new LogicException('CSRF lifecycle requires a session after authentication state changes.');
        }

        return $session;
    }
}
