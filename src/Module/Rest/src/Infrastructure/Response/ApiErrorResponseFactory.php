<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Response;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class ApiErrorResponseFactory
{
    /**
     * @param null|array<string, mixed>                     $details
     * @param array<non-empty-string, array<string>|string> $headers
     */
    public function error(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
        array $headers = [],
    ): ResponseInterface {
        $payload = [
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        if (null !== $details && [] !== $details) {
            $payload['error']['details'] = $details;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    public function unauthorized(string $message = 'Authentication required.'): ResponseInterface
    {
        return $this->error(
            'authentication_required',
            $message,
            401,
            headers: [
                'WWW-Authenticate' => 'Bearer',
            ],
        );
    }

    public function forbidden(string $message = 'Forbidden.'): ResponseInterface
    {
        return $this->error('forbidden', $message, 403);
    }

    public function emailVerificationRequired(): ResponseInterface
    {
        return $this->error('email_verification_required', 'Email verification required.', 403);
    }

    public function accountBanned(?string $reason, ?string $bannedAt): ResponseInterface
    {
        return $this->error('account_banned', 'Account has been banned.', 403, [
            'reason'    => $reason,
            'banned_at' => $bannedAt,
        ]);
    }

    public function notFound(string $message = 'Resource not found.'): ResponseInterface
    {
        return $this->error('not_found', $message, 404);
    }

    /**
     * @param array<string, list<string>> $details
     */
    public function validation(array $details, string $message = 'Invalid request data.'): ResponseInterface
    {
        return $this->error('validation_failed', $message, 422, $details);
    }

    public function disabled(string $message = 'API documentation is disabled.'): ResponseInterface
    {
        return $this->error('not_found', $message, 404);
    }
}
