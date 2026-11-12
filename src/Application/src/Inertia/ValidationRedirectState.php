<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

use Mezzio\Session\RetrieveSession;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;

final readonly class ValidationRedirectState
{
    private const string ERRORS_KEY = '_packio_validation_errors';
    private const string OLD_KEY    = '_packio_validation_old';

    /** @param array<string, mixed> $errors */
    public function errors(ServerRequestInterface $serverRequest, array $errors): void
    {
        $this->session($serverRequest)?->set(self::ERRORS_KEY, $errors);
    }

    /** @param array<string, mixed> $input */
    public function old(ServerRequestInterface $serverRequest, array $input): void
    {
        $this->session($serverRequest)?->set(self::OLD_KEY, $input);
    }

    /** @return array<string, mixed> */
    public function consumeErrors(ServerRequestInterface $serverRequest): array
    {
        return $this->consume($serverRequest, self::ERRORS_KEY);
    }

    /** @return array<string, mixed> */
    public function consumeOld(ServerRequestInterface $serverRequest): array
    {
        return $this->consume($serverRequest, self::OLD_KEY);
    }

    /** @return array<string, mixed> */
    private function consume(ServerRequestInterface $serverRequest, string $key): array
    {
        $session = $this->session($serverRequest);
        if (! $session instanceof SessionInterface) {
            return [];
        }

        $value = $session->get($key, []);
        $session->unset($key);

        return is_array($value) ? $value : [];
    }

    private function session(ServerRequestInterface $serverRequest): ?SessionInterface
    {
        return RetrieveSession::fromRequestOrNull($serverRequest);
    }
}
