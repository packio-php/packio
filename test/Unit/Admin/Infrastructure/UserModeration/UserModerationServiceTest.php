<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\UserModeration;

use Packio\Admin\Infrastructure\UserModeration\UserModerationService;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Identity\Api\Dto\UserModerationOutcome;
use Packio\Identity\Api\Dto\UserModerationResultDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function json_encode;

#[CoversClass(UserModerationService::class)]
final class UserModerationServiceTest extends TestCase
{
    #[Test]
    public function historyRequestsOnlyModerationEventsBeforeApplyingTheLimit(): void
    {
        $audit = $this->createMock(AuditApiFacadeInterface::class);
        $audit->expects(self::once())
            ->method('recentBySubjectAndEvents')
            ->with('user', 'user-1', [
                'identity.user.banned',
                'identity.user.unbanned',
            ], 20)
            ->willReturn([
                $this->entry('identity.user.banned'),
                $this->entry('identity.user.unbanned'),
            ])
        ;

        $userModerationService = new UserModerationService(
            $this->createStub(IdentityApiFacadeInterface::class),
            $audit,
            $this->createStub(LoggerInterface::class),
        );

        self::assertCount(2, $userModerationService->history('user-1'));
    }

    #[Test]
    public function successfulBanRecordsOneAuditEventAndDoesNotLogSensitiveModerationInput(): void
    {
        $reason   = 'Violation of project rules.';
        $identity = $this->createMock(IdentityApiFacadeInterface::class);
        $audit    = $this->createMock(AuditApiFacadeInterface::class);
        $logger   = $this->createMock(LoggerInterface::class);

        $identity->expects(self::once())
            ->method('banUser')
            ->with('user-1', 'admin-1', $reason)
            ->willReturn(new UserModerationResultDto(UserModerationOutcome::Success))
        ;
        $audit->expects(self::once())
            ->method('record')
            ->with(
                'identity.user.banned',
                [
                    'reason' => $reason,
                ],
                '',
                '',
                '',
                'admin-1',
                'user',
                'user-1',
            )
        ;
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'User moderation completed.',
                self::callback(static function(array $context) use ($reason): bool {
                    self::assertArrayNotHasKey('reason', $context);
                    self::assertArrayNotHasKey('token', $context);
                    self::assertArrayNotHasKey('cookie', $context);
                    self::assertStringNotContainsString($reason, (string) json_encode($context));

                    return true;
                }),
            )
        ;

        $userModerationService = new UserModerationService($identity, $audit, $logger);

        self::assertSame(
            UserModerationOutcome::Success,
            $userModerationService->ban('admin-1', 'user-1', $reason)->outcome,
        );
    }

    private function entry(string $event): AuditEntryDto
    {
        return new AuditEntryDto(
            id: '00000000-0000-4000-8000-000000000001',
            event: $event,
            occurredAt: '2026-08-22T10:00:00+00:00',
            context: [],
        );
    }
}
