<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Support;

use Common\Logging\SafeExceptionContext;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function mb_substr;

final readonly class SignInRecorder
{
    public const string METHOD_PASSWORD = 'password';

    public function __construct(
        private UserSignInRepositoryInterface $userSignInRepository,
        private ClientIpResolverInterface $clientIpResolver,
        private LoggerInterface $logger,
    ) {}

    /** A failed history write must never break the sign-in itself. */
    public function record(ServerRequestInterface $serverRequest, string $userId, string $method): void
    {
        try {
            $userAgent = (string) ($serverRequest->getServerParams()['HTTP_USER_AGENT'] ?? '');

            $this->userSignInRepository->save(new UserSignIn(
                $userId,
                $method,
                $this->clientIpResolver->resolve($serverRequest),
                '' === $userAgent ? null : mb_substr($userAgent, 0, 255),
            ));
        } catch (Throwable $exception) {
            $this->logger->error('Sign-in history record failed.', SafeExceptionContext::forRequest($exception, $serverRequest));
        }
    }
}
