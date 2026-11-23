<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Authentication;

use Packio\Identity\Infrastructure\Support\SignInRecorder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;

use function is_string;

/** Records a sign-in when the login payload carries PAYLOAD_KEY; the key never reaches the inner manager. */
final readonly class SignInRecordingAuthManager implements AuthManagerInterface
{
    public const string PAYLOAD_KEY = 'sign_in_method';

    public function __construct(
        private AuthManagerInterface $authManager,
        private SignInRecorder $signInRecorder,
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
        $method = $payload[self::PAYLOAD_KEY] ?? null;
        $userId = $payload['user_id'] ?? null;
        if (! is_string($method) || ! is_string($userId)) {
            return $this->authManager->login($serverRequest, $response, $payload, $expiresAt);
        }

        unset($payload[self::PAYLOAD_KEY]);
        $response = $this->authManager->login($serverRequest, $response, $payload, $expiresAt);
        $this->signInRecorder->record($serverRequest, $userId, $method);

        return $response;
    }

    public function logout(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        return $this->authManager->logout($serverRequest, $response);
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
}
