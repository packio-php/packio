<?php

declare(strict_types=1);

namespace PackioTest\Integration\Audit\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Domain\Repository\AuditRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function usort;

final class AuditRepositoryRecentTest extends IntegrationTestCase
{
    // Far-future timestamps keep these entries ahead of anything else in the table.
    private const string T1 = '2090-01-01 10:00:00';

    private const string T2 = '2090-01-01 11:00:00';

    private const string T3 = '2090-01-01 12:00:00';

    #[Test]
    public function testRecentReturnsNewestEntriesAcrossOrganizations(): void
    {
        $repository = $this->repository();
        $repository->save($this->entry('t1', self::T1, 'org-a'));
        $repository->save($this->entry('t2', self::T2, 'org-b'));
        $repository->save($this->entry('t3', self::T3, 'org-a'));

        self::assertSame(['t3', 't2'], array_map(
            static fn (AuditEntry $auditEntry): string => $auditEntry->subjectId(),
            $repository->recent(2),
        ));
    }

    #[Test]
    public function testRecentBreaksTiesById(): void
    {
        $repository = $this->repository();
        $first      = $repository->save($this->entry('tie-1', self::T3, 'org-a'));
        $second     = $repository->save($this->entry('tie-2', self::T3, 'org-a'));

        $expected = [$first, $second];
        usort($expected, static fn (AuditEntry $a, AuditEntry $b): int => (string) $b->getIdentifier() <=> (string) $a->getIdentifier());

        self::assertSame(
            array_map(static fn (AuditEntry $auditEntry): string => (string) $auditEntry->getIdentifier(), $expected),
            array_map(static fn (AuditEntry $auditEntry): string => (string) $auditEntry->getIdentifier(), $repository->recent(2)),
        );
    }

    #[Test]
    public function testFacadeRecentExposesTheOrganizationId(): void
    {
        $this->repository()->save($this->entry('facade', '2091-01-01 00:00:00', 'org-facade'));

        $facade = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $facade);

        $entries = $facade->recent(1);
        self::assertSame('org-facade', $entries[0]->organizationId);
    }

    private function repository(): AuditRepositoryInterface
    {
        $repository = $this->getService(AuditRepositoryInterface::class);
        self::assertInstanceOf(AuditRepositoryInterface::class, $repository);

        return $repository;
    }

    private function entry(string $subjectId, string $occurredAt, string $organizationId): AuditEntry
    {
        return new AuditEntry(
            event: 'test.audit.recent',
            occurredAt: new Chronos($occurredAt, 'UTC'),
            organizationId: $organizationId,
            subjectType: 'audit_recent_test',
            subjectId: $subjectId,
        );
    }
}
