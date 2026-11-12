<?php

declare(strict_types=1);

namespace Packio\Application\Security\Turnstile;

use Packio\Application\Security\Honeypot\HoneypotValidator;
use Packio\Application\Security\Honeypot\HoneypotValidatorInterface;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;
use function trim;

final readonly class TurnstileRequestGuard
{
    public const string TOKEN_FIELD = 'cf-turnstile-response';
    public const string ERROR_FIELD = 'turnstile';

    private bool $enabled;
    private string $siteKey;

    /** @param array<string, mixed> $config */
    public function __construct(
        private TurnstileVerifierInterface $turnstileVerifier,
        private ClientIpResolverInterface $clientIpResolver,
        array $config,
        private HoneypotValidatorInterface $honeypotValidator = new HoneypotValidator()
    ) {
        $this->siteKey = trim((string) ($config['site_key'] ?? ''));
        $secretKey     = trim((string) ($config['secret_key'] ?? ''));
        $this->enabled = (bool) ($config['enabled'] ?? false) && '' !== $this->siteKey && '' !== $secretKey;
    }

    /** @return array{enabled: bool, site_key: string} */
    public function payload(): array
    {
        if (! $this->enabled) {
            return [
                'enabled'  => false,
                'site_key' => '',
            ];
        }

        return [
            'enabled'  => true,
            'site_key' => $this->siteKey,
        ];
    }

    public function verify(ServerRequestInterface $serverRequest): bool
    {
        if (! $this->honeypotValidator->validate($serverRequest)) {
            return false;
        }

        if (! $this->enabled) {
            return true;
        }

        $token = $this->tokenFromRequest($serverRequest);
        if (null === $token) {
            return false;
        }

        return $this->turnstileVerifier->verify($token, $this->clientIpResolver->resolve($serverRequest));
    }

    /** @return array<string, list<string>> */
    public function error(): array
    {
        return [
            self::ERROR_FIELD => ['Please complete the security challenge.'],
        ];
    }

    private function tokenFromRequest(ServerRequestInterface $serverRequest): ?string
    {
        $body = $serverRequest->getParsedBody();
        if (! is_array($body)) {
            return null;
        }

        $token = $body[self::TOKEN_FIELD] ?? null;
        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);

        return '' === $token ? null : $token;
    }
}
