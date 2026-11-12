<?php

declare(strict_types=1);

namespace Packio\Application\Security\Csrf;

use Mezzio\Session\SessionInterface;

use function bin2hex;
use function hash_equals;
use function is_string;
use function preg_match;
use function random_bytes;
use function strlen;

final class CsrfTokenManager
{
    public function getOrCreate(SessionInterface $session): string
    {
        $token = $session->get(CsrfTokenContract::SESSION_KEY);
        if ($this->isValidToken($token)) {
            return $token;
        }

        return $this->rotate($session);
    }

    public function validate(SessionInterface $session, mixed $submittedToken): bool
    {
        $storedToken = $session->get(CsrfTokenContract::SESSION_KEY);

        return $this->isValidToken($storedToken)
            && $this->isValidToken($submittedToken)
            && hash_equals($storedToken, $submittedToken);
    }

    public function rotate(SessionInterface $session): string
    {
        $token = bin2hex(random_bytes(CsrfTokenContract::RANDOM_BYTES_LENGTH));

        $session->set(CsrfTokenContract::SESSION_KEY, $token);

        return $token;
    }

    public function clear(SessionInterface $session): void
    {
        $session->unset(CsrfTokenContract::SESSION_KEY);
    }

    private function isValidToken(mixed $token): bool
    {
        return is_string($token)
            && CsrfTokenContract::TOKEN_LENGTH === strlen($token)
            && 1 === preg_match('/\A[a-f0-9]{' . CsrfTokenContract::TOKEN_LENGTH . '}\z/D', $token);
    }
}
