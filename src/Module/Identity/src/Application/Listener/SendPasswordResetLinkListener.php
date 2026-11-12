<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Listener;

use Common\Branding\BrandingConfig;
use Common\Mail\MailerInterface;
use Packio\Identity\Application\Event\PasswordResetRequested;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function ceil;
use function max;
use function rtrim;
use function sprintf;

final readonly class SendPasswordResetLinkListener
{
    /**
     * @param array<string, mixed> $passwordResetConfig
     */
    public function __construct(
        private MailerInterface $mailer,
        private AuthEmailTemplateRendererInterface $authEmailTemplateRenderer,
        private BrandingConfig $brandingConfig,
        private array $passwordResetConfig,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(PasswordResetRequested $passwordResetRequested): void
    {
        try {
            $resetUrl = sprintf(
                '%s/auth/reset-password/%s/%s',
                rtrim($this->brandingConfig->appBaseUrl(), '/'),
                $passwordResetRequested->selector,
                $passwordResetRequested->secret,
            );

            $email = $this->authEmailTemplateRenderer->renderPasswordResetLink(
                resetUrl: $resetUrl,
                ttlMinutes: $this->tokenTtlMinutes(),
            );

            $this->mailer->send(
                to: $passwordResetRequested->userEmail,
                subject: $email->subject,
                html: $email->html,
            );
        } catch (Throwable $e) {
            $this->logger?->error('Failed to send password reset link', [
                'user_uuid'       => $passwordResetRequested->userUuid->toString(),
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }

    private function tokenTtlMinutes(): int
    {
        $ttlSeconds = (int) ($this->passwordResetConfig['token_ttl_seconds'] ?? 3600);

        return max(1, (int) ceil(($ttlSeconds > 0 ? $ttlSeconds : 3600) / 60));
    }
}
