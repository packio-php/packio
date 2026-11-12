<?php

declare(strict_types=1);

namespace Packio\Application\Security\Turnstile;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;
use function trim;

final readonly class TurnstileVerifier implements TurnstileVerifierInterface
{
    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private string $expectedHostname;

    public function __construct(
        private ClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
        private string $secretKey = '',
        private string $verifyUrl = self::VERIFY_URL,
        string $expectedHostname = '',
        private bool $validateHostname = true,
    ) {
        $this->expectedHostname = trim($expectedHostname);
    }

    public function verify(string $token, ?string $ip = null): bool
    {
        if ('' === $this->secretKey || '' === $this->verifyUrl) {
            $this->logger?->warning('Turnstile verification skipped — not configured.');

            return false;
        }

        $formParams = [
            'secret'   => $this->secretKey,
            'response' => $token,
        ];
        if (null !== $ip && '' !== trim($ip)) {
            $formParams['remoteip'] = $ip;
        }

        try {
            $response = $this->httpClient->request('POST', $this->verifyUrl, [
                'form_params' => $formParams,
            ]);

            return $this->isSuccess($response);
        } catch (Throwable $e) {
            $this->logger?->warning('Turnstile verification request failed.', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function isSuccess(ResponseInterface $response): bool
    {
        $body = json_decode($response->getBody()->getContents(), true);
        if (! is_array($body)) {
            return false;
        }

        $success = ($body['success'] ?? false) === true;
        if (! $success) {
            $this->logger?->warning('Turnstile verification rejected.', [
                'error_codes' => $body['error-codes'] ?? [],
            ]);

            return false;
        }

        if ($this->validateHostname && '' !== $this->expectedHostname) {
            $responseHostname = is_string($body['hostname'] ?? null) ? $body['hostname'] : null;
            if (null === $responseHostname || $responseHostname !== $this->expectedHostname) {
                $this->logger?->warning('Turnstile hostname mismatch.', [
                    'expected' => $this->expectedHostname,
                    'received' => $responseHostname,
                ]);

                return false;
            }
        }

        return true;
    }
}
