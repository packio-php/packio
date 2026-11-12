<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\UserModeration;

use InvalidArgumentException;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Identity\Api\Dto\UserModerationOutcome;
use Packio\Identity\Api\Dto\UserModerationResultDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Log\LoggerInterface;

use function mb_strlen;
use function trim;

final readonly class UserModerationService
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
        private LoggerInterface $logger,
    ) {}

    public function ban(string $adminUserId, string $targetUserId, string $reason): UserModerationResultDto
    {
        $userModerationResultDto = $this->identityApiFacade->banUser($targetUserId, $adminUserId, $reason);
        $this->logOutcome($adminUserId, $targetUserId, 'ban', $userModerationResultDto->outcome);

        if ($userModerationResultDto->isSuccess()) {
            $this->auditApiFacade->record(
                event: 'identity.user.banned',
                context: [
                    'reason' => $reason,
                ],
                actorId: $adminUserId,
                subjectType: 'user',
                subjectId: $targetUserId,
            );
        }

        return $userModerationResultDto;
    }

    public function unban(string $adminUserId, string $targetUserId, string $note): UserModerationResultDto
    {
        $note = trim($note);
        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            throw new InvalidArgumentException('Unban note has an invalid length.');
        }

        $userModerationResultDto = $this->identityApiFacade->unbanUser($targetUserId, $adminUserId);
        $this->logOutcome($adminUserId, $targetUserId, 'unban', $userModerationResultDto->outcome);

        if ($userModerationResultDto->isSuccess()) {
            $this->auditApiFacade->record(
                event: 'identity.user.unbanned',
                context: [
                    'note' => $note,
                ],
                actorId: $adminUserId,
                subjectType: 'user',
                subjectId: $targetUserId,
            );
        }

        return $userModerationResultDto;
    }

    /** @return list<AuditEntryDto> */
    public function history(string $userId, int $limit = 20): array
    {
        return $this->auditApiFacade->recentBySubjectAndEvents('user', $userId, [
            'identity.user.banned',
            'identity.user.unbanned',
        ], $limit);
    }

    private function logOutcome(
        string $adminUserId,
        string $targetUserId,
        string $action,
        UserModerationOutcome $userModerationOutcome
    ): void {
        $context = [
            'admin_user_id'  => $adminUserId,
            'target_user_id' => $targetUserId,
            'action'         => $action,
            'outcome'        => $userModerationOutcome->value,
        ];

        if (UserModerationOutcome::Success === $userModerationOutcome) {
            $this->logger->info('User moderation completed.', $context);

            return;
        }

        $this->logger->warning('User moderation rejected.', $context);
    }
}
