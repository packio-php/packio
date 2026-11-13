<?php

declare(strict_types=1);

namespace PackioTest\Integration\Audit\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use Packio\Audit\Infrastructure\Cli\AuditLogPruneCommand;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditLogPruneCommandTest extends IntegrationTestCase
{
    #[Test]
    public function itDeletesOnlyEntriesOlderThanTheConfiguredRetention(): void
    {
        $this->saveEntry('old', 181);
        $this->saveEntry('recent', 179);

        $commandTester = $this->command();
        self::assertSame(Command::SUCCESS, $commandTester->execute([]));

        self::assertStringContainsString('older_than_days=180 deleted=1', $commandTester->getDisplay());
        self::assertSame(['recent'], $this->remainingSubjectIds());
    }

    #[Test]
    public function dryRunWithAnOverrideCountsWithoutDeleting(): void
    {
        $this->saveEntry('old', 31);
        $this->saveEntry('recent', 29);

        $commandTester = $this->command();
        self::assertSame(Command::SUCCESS, $commandTester->execute([
            '--older-than-days' => '30',
            '--dry-run'         => true,
        ]));

        self::assertStringContainsString('dry_run=1', $commandTester->getDisplay());
        self::assertCount(2, $this->remainingSubjectIds());
    }

    #[Test]
    public function itRejectsANonPositiveRetention(): void
    {
        $this->saveEntry('old', 400);

        self::assertSame(Command::INVALID, $this->command()->execute([
            '--older-than-days' => '0',
        ]));
        self::assertSame(['old'], $this->remainingSubjectIds());
    }

    #[Test]
    public function itDeletesInBatchesUntilNothingOldIsLeft(): void
    {
        foreach (['a', 'b', 'c'] as $id) {
            $this->saveEntry($id, 200);
        }

        $repository = $this->getService(AuditRepositoryInterface::class);
        self::assertInstanceOf(AuditRepositoryInterface::class, $repository);

        self::assertSame(3, $repository->deleteOlderThan(Chronos::now('UTC')->subDays(180), 2));
        self::assertSame([], $this->remainingSubjectIds());
    }

    private function command(): CommandTester
    {
        $command = $this->getService(AuditLogPruneCommand::class);
        self::assertInstanceOf(AuditLogPruneCommand::class, $command);

        return new CommandTester($command);
    }

    private function saveEntry(string $subjectId, int $daysAgo): void
    {
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $audit);
        $audit->record(event: 'test.audit.prune', context: [], subjectType: 'audit_prune_test', subjectId: $subjectId);

        // record() stamps occurred_at with the real clock, so set it directly.
        $this->database()->update('audit_entries')->values([
            'occurred_at' => Chronos::now('UTC')->subDays($daysAgo)->toDateTimeString(),
        ])->where('subject_type', 'audit_prune_test')->where('subject_id', $subjectId)->run();
    }

    /**
     * @return list<string>
     */
    private function remainingSubjectIds(): array
    {
        $ids = [];
        foreach ($this->database()->select('subject_id')->from('audit_entries')->where('subject_type', 'audit_prune_test')->orderBy('subject_id')->fetchAll() as $row) {
            $ids[] = (string) $row['subject_id'];
        }

        return $ids;
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
