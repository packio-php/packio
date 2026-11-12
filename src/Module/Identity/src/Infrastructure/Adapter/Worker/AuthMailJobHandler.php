<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Adapter\Worker;

use Common\Security\SecretEncryptorInterface;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\PasswordResetService;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Throwable;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function time;

final readonly class AuthMailJobHandler implements JobHandlerInterface
{
    public function __construct(
        private SecretEncryptorInterface $secretEncryptor,
        private EmailVerificationService $emailVerificationService,
        private PasswordResetService $passwordResetService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
    {
        try {
            $encrypted = $payload['encrypted_request'] ?? null;
            $plaintext = is_string($encrypted) && $this->secretEncryptor->isEncrypted($encrypted) ? $this->secretEncryptor->decrypt($encrypted) : null;
            $request   = null !== $plaintext ? json_decode($plaintext, true, 16, JSON_THROW_ON_ERROR) : null;
        } catch (Throwable) {
            return JobResult::failure('Invalid authentication mail request.', shouldRetry: false);
        }
        if (! is_array($request) || ! is_int($request['expires_at'] ?? null)) {
            return JobResult::failure('Invalid authentication mail request.', shouldRetry: false);
        }

        if ($request['expires_at'] < time()) {
            return JobResult::success();
        }

        try {
            if ('verification' === ($request['action'] ?? null) && is_string($request['user_id'] ?? null) && is_bool($request['force'] ?? null)) {
                $this->emailVerificationService->sendVerificationCode($request['user_id'], $request['force']);
            } elseif ('password_reset' === ($request['action'] ?? null) && is_string($request['email'] ?? null)) {
                $this->passwordResetService->requestResetByEmail($request['email'], is_string($request['ip'] ?? null) ? $request['ip'] : null, is_string($request['user_agent'] ?? null) ? $request['user_agent'] : null);
            } else {
                return JobResult::failure('Invalid authentication mail request.', shouldRetry: false);
            }
        } catch (Throwable) {
            // The worker persists this message: never include SMTP responses, addresses or tokens.
            return JobResult::failure('Authentication email delivery failed.');
        }

        return JobResult::success();
    }
}
