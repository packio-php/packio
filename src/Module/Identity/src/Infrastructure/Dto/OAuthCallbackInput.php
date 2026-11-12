<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Dto;

use Psr\Http\Message\ServerRequestInterface;

use function is_string;

final readonly class OAuthCallbackInput
{
    public function __construct(
        public string $receivedState,
        public string $code,
        public string $error,
        public string $errorDescription,
        public ?OAuthState $decodedState,
    ) {}

    public static function fromRequest(ServerRequestInterface $serverRequest): self
    {
        $queryParams   = $serverRequest->getQueryParams();
        $receivedState = is_string($queryParams['state'] ?? null) ? $queryParams['state'] : '';

        return new self(
            receivedState: $receivedState,
            code: is_string($queryParams['code'] ?? null) ? $queryParams['code'] : '',
            error: is_string($queryParams['error'] ?? null) ? $queryParams['error'] : 'unknown',
            errorDescription: is_string($queryParams['error_description'] ?? null) ? $queryParams['error_description'] : 'Authorization failed.',
            decodedState: OAuthState::decode($receivedState),
        );
    }
}
