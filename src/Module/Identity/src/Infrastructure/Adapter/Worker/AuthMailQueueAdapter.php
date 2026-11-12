<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Adapter\Worker;

use Common\Security\SecretEncryptorInterface;
use Packio\Identity\Application\Port\AuthMailQueuePort;
use Packio\Worker\Api\JobApiFacadeInterface;

use function json_encode;
use function substr;
use function time;

final readonly class AuthMailQueueAdapter implements AuthMailQueuePort
{
    // Organization identifiers are UUIDs; this reserved scope cannot belong to a tenant.
    public const string SYSTEM_SCOPE = 'system:identity';
    public const string TYPE         = 'identity.auth_mail';

    public function __construct(private JobApiFacadeInterface $jobApiFacade, private SecretEncryptorInterface $secretEncryptor) {}

    public function verification(string $userId, bool $force): void
    {
        $this->enqueue([
            'action'  => 'verification',
            'user_id' => $userId,
            'force'   => $force,
        ]);
    }

    public function passwordReset(string $email, ?string $ip, ?string $userAgent): void
    {
        // Do not look up the account on the public request path.
        $this->enqueue([
            'action'     => 'password_reset',
            'email'      => $email,
            'ip'         => $ip,
            'user_agent' => null === $userAgent ? null : substr($userAgent, 0, 1024),
        ]);
    }

    /** @param array<string, mixed> $request */
    private function enqueue(array $request): void
    {
        $request['expires_at'] = time() + 3600;
        $this->jobApiFacade->enqueue(self::SYSTEM_SCOPE, self::TYPE, [
            'encrypted_request' => $this->secretEncryptor->encrypt(json_encode($request, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)),
        ], priority: 10);
    }
}
