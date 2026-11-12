<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Authentication;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\Mezzio\Authentication\Contract\TokenInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;

use function preg_match;
use function trim;

/**
 * Transports API credentials only; issuing and revoking tokens remain Identity use cases.
 */
final readonly class ApiAccessTokenTransport implements TokenTransportInterface
{
    public function fetch(ServerRequestInterface $serverRequest): ?string
    {
        $bearerToken = $this->bearerToken($serverRequest->getHeaderLine('Authorization'));
        if (null !== $bearerToken) {
            return $bearerToken;
        }

        $legacyToken = trim($serverRequest->getHeaderLine('X-API-TOKEN'));

        return '' === $legacyToken ? null : $legacyToken;
    }

    public function attach(ResponseInterface $response, TokenInterface $token): ResponseInterface
    {
        return $response->withHeader('Authorization', 'Bearer ' . $token->getId());
    }

    public function detach(ResponseInterface $response): ResponseInterface
    {
        return $response->withoutHeader('Authorization');
    }

    private function bearerToken(string $header): ?string
    {
        if (1 !== preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return '' === $token ? null : $token;
    }
}
