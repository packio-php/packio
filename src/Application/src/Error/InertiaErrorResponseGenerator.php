<?php

declare(strict_types=1);

namespace Packio\Application\Error;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Stratigility\Utils;
use Mezzio\Template\TemplateRendererInterface;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Service\InitialThemeResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\InertiaPsr15\Service\InertiaFactoryInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Sirix\Mezzio\Rbac\Exception\AuthorizationException;
use Throwable;

use function strtolower;

final readonly class InertiaErrorResponseGenerator
{
    private const string FALLBACK_TEMPLATE      = 'errors/default.twig';
    private const string AUTHENTICATED_REDIRECT = '/organizations';
    private const string LOGIN_REDIRECT         = '/auth/login';

    private string $template;
    private string $layout;

    public function __construct(
        private InertiaFactoryInterface $inertiaFactory,
        private InertiaVersionProviderInterface $inertiaVersionProvider,
        private bool $debug,
        private TemplateRendererInterface $templateRenderer,
        private BrandingConfig $brandingConfig,
        private InitialThemeResolver $initialThemeResolver,
        private AuthorizationExceptionResponseFactory $authorizationExceptionResponseFactory,
        string $template = self::FALLBACK_TEMPLATE,
        string $layout = 'layout::default',
    ) {
        $this->template = '' !== $template ? $template : 'errors/default.twig';
        $this->layout   = '' !== $layout ? $layout : 'layout::default';
    }

    public function __invoke(Throwable $throwable, ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        if ($this->isApiRequest($serverRequest)) {
            if ($throwable instanceof AuthorizationException) {
                $authorizationResponse = $this->authorizationExceptionResponseFactory->create($throwable, $serverRequest);
                if ($authorizationResponse instanceof ResponseInterface) {
                    return $authorizationResponse;
                }
            }

            return $this->apiErrorResponse($throwable, $response);
        }

        if ($throwable instanceof AlreadyAuthenticatedException) {
            return new RedirectResponse(self::AUTHENTICATED_REDIRECT);
        }

        if ($throwable instanceof AuthenticationException) {
            return new RedirectResponse(self::LOGIN_REDIRECT);
        }

        if ($throwable instanceof AuthorizationException) {
            $authorizationResponse = $this->authorizationExceptionResponseFactory->create($throwable, $serverRequest);
            if ($authorizationResponse instanceof ResponseInterface) {
                return $authorizationResponse;
            }
        }

        $status = $throwable instanceof AuthorizationException
            ? $throwable->getStatusCode()
            : Utils::getStatusCode($throwable, $response);

        if ($status >= 500 && $serverRequest->hasHeader('X-Inertia')) {
            $inertia = $this->inertiaFactory->fromRequest(
                $serverRequest,
                $this->inertiaVersionProvider->currentVersion($serverRequest),
            );

            return $inertia->render('Errors/ServerError', [
                'status'        => $status,
                'title'         => $this->titleForStatus($status),
                'message'       => $this->messageForStatus($status, $throwable->getMessage()),
                'requestPath'   => $serverRequest->getUri()->getPath(),
                'branding'      => $this->branding(),
                'initial_theme' => $this->initialTheme($serverRequest),
                'debug'         => $this->debug,
                ...($this->debug ? [
                    'exceptionClass' => $throwable::class,
                    'file'           => $throwable->getFile(),
                    'line'           => $throwable->getLine(),
                    'trace'          => $throwable->getTraceAsString(),
                ] : []),
            ])
                ->withStatus($status)
                ->withHeader('X-Inertia', 'true')
                ->withAddedHeader('Vary', 'X-Inertia')
            ;
        }

        $response = $response->withStatus($status);
        $response->getBody()->write($this->templateRenderer->render($this->template, [
            'response'      => $response,
            'request'       => $serverRequest,
            'uri'           => (string) $serverRequest->getUri(),
            'status'        => $response->getStatusCode(),
            'reason'        => $response->getReasonPhrase(),
            'layout'        => $this->layout,
            'branding'      => $this->branding(),
            'initial_theme' => $this->initialTheme($serverRequest),
            ...($this->debug ? [
                'error' => $throwable,
            ] : []),
        ]));

        return $response;
    }

    private function isApiRequest(ServerRequestInterface $serverRequest): bool
    {
        return ApiPathMatcher::isApiPath(strtolower($serverRequest->getUri()->getPath()));
    }

    private function apiErrorResponse(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $status  = $this->apiStatus($throwable, $response);
        $payload = [
            'error' => [
                'code'    => $this->apiErrorCode($status),
                'message' => $this->apiErrorMessage($status, $throwable),
            ],
        ];

        if ($this->debug) {
            $payload['error']['details'] = [
                'exception' => $throwable::class,
                'message'   => $throwable->getMessage(),
                'file'      => $throwable->getFile(),
                'line'      => $throwable->getLine(),
            ];
        }

        return new JsonResponse($payload, $status);
    }

    private function apiStatus(Throwable $throwable, ResponseInterface $response): int
    {
        if ($throwable instanceof AuthenticationException) {
            return 401;
        }

        if ($throwable instanceof AlreadyAuthenticatedException) {
            return 409;
        }

        $status = $throwable instanceof AuthorizationException
            ? $throwable->getStatusCode()
            : Utils::getStatusCode($throwable, $response);

        return $status >= 400 && $status <= 599 ? $status : 500;
    }

    private function apiErrorCode(int $status): string
    {
        return match ($status) {
            401     => 'authentication_required',
            403     => 'forbidden',
            404     => 'not_found',
            405     => 'method_not_allowed',
            409     => 'conflict',
            422     => 'validation_failed',
            429     => 'too_many_requests',
            default => $status >= 500 ? 'internal_server_error' : 'request_failed',
        };
    }

    private function apiErrorMessage(int $status, Throwable $throwable): string
    {
        if ($status >= 500) {
            return $this->debug && '' !== $throwable->getMessage()
                ? $throwable->getMessage()
                : 'The request could not be completed.';
        }

        if ('' !== $throwable->getMessage()) {
            return $throwable->getMessage();
        }

        return match ($status) {
            401     => 'Authentication required.',
            403     => 'Forbidden.',
            404     => 'Resource not found.',
            405     => 'Method not allowed.',
            409     => 'Conflict.',
            422     => 'Invalid request data.',
            429     => 'Too many requests.',
            default => 'The request could not be completed.',
        };
    }

    /** @return array<string, string> */
    private function branding(): array
    {
        return $this->brandingConfig->toArray();
    }

    /** @return array<string, null|string> */
    private function initialTheme(ServerRequestInterface $serverRequest): array
    {
        return $this->initialThemeResolver->resolve(
            $serverRequest,
            $this->brandingConfig->themeStorageKey(),
        );
    }

    private function titleForStatus(int $status): string
    {
        if ($status >= 500) {
            return 'Server error';
        }

        return match ($status) {
            404     => 'Page not found',
            405     => 'Method not allowed',
            419     => 'Page expired',
            422     => 'Validation failed',
            default => 'Request failed',
        };
    }

    private function messageForStatus(int $status, string $message): string
    {
        if ($status >= 500) {
            return $this->debug
                ? $message
                : 'Something went wrong while processing your request.';
        }

        return match ($status) {
            404     => 'The page you requested does not exist or has moved.',
            419     => 'Your session or CSRF token expired. Reload the page and try again.',
            default => '' !== $message
                ? $message
                : 'The request could not be completed.',
        };
    }
}
