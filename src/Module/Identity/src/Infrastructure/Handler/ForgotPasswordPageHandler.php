<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Packio\Application\Security\Honeypot\HoneypotFieldNameGenerator;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/auth/forgot-password', name: 'auth.forgot-password')]
final readonly class ForgotPasswordPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private TurnstileRequestGuard $turnstileRequestGuard,
        private HoneypotFieldNameGenerator $honeypotFieldNameGenerator
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        return $inertia->render('Auth/ForgotPassword', [
            'turnstile' => $this->turnstileRequestGuard->payload(),
            'honeypot'  => [
                'field_name' => $this->honeypotFieldNameGenerator->generate(),
            ],
        ]);
    }
}
