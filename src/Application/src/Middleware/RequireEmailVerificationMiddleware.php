<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_any;
use function str_starts_with;

final readonly class RequireEmailVerificationMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const array EXEMPT_PATHS = [
        '/auth/verification',
        '/auth/logout',
        '/auth/login',
        '/auth/register',
        '/account/banned',
    ];

    public function __construct(
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession()
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if (array_any(self::EXEMPT_PATHS, fn ($exemptPath) => $path === $exemptPath || str_starts_with($path, $exemptPath . '/'))) {
            return $handler->handle($request);
        }

        $user = RequestContextAttributes::currentUser($request);
        if ($user instanceof UserDto && ! $user->isEmailVerified) {
            $session = $request->getAttribute('session');
            if ($session instanceof SessionInterface) {
                $this->pendingEmailVerificationSession->start($session, $user->id, $path);
            }

            return new RedirectResponse('/auth/verification');
        }

        return $handler->handle($request);
    }
}
