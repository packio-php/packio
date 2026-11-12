<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Application\Command;

use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome as Outcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult as Result;
use Packio\Organization\Application\Command\OrganizationDeletionInitiator;
use Packio\Organization\Application\Port\OrganizationDeletionInitiationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class OrganizationDeletionInitiatorTest extends TestCase
{
    #[DataProvider('publicationOutcomes')]
    public function testAuditsBeforePublication(Outcome $initial, bool $published, Outcome $expected): void
    {
        $port                         = $this->createMock(OrganizationDeletionInitiationPort::class);
        $organizationDeletionContext  = new OrganizationDeletionContext('org', 'actor');
        $port->expects(self::once())->method('deleteOrganizationWithOutcome')->with('org', 'actor')
            ->willReturn(new Result($initial, 'job', false, 'run'))
        ;
        $audited = false;
        $port->expects(self::once())->method('recordAudit')->with($organizationDeletionContext, 'run')->willReturnCallback(static function() use (&$audited): bool {
            $audited = true;

            return true;
        });
        $port->expects(self::once())->method('publishInitiationAfterAudit')->with('org', 'job')->willReturnCallback(static function() use (&$audited, $published): bool {
            self::assertTrue($audited);

            return $published;
        });
        $port->expects(self::never())->method('revertInitiation');
        $organizationDeletionInitiationResult = new OrganizationDeletionInitiator($port, new NullLogger())->initiate($organizationDeletionContext);
        self::assertSame($expected, $organizationDeletionInitiationResult->outcome);
        self::assertSame('job', $organizationDeletionInitiationResult->jobId);
        self::assertSame('run', $organizationDeletionInitiationResult->deletionRunId);
    }

    /** @return iterable<string, array{Outcome, bool, Outcome}> */
    public static function publicationOutcomes(): iterable
    {
        yield 'new' => [Outcome::Initiated, true, Outcome::Initiated];

        yield 'repeat' => [Outcome::AlreadyDeleting, true, Outcome::AlreadyInProgress];

        yield 'publication failed' => [Outcome::Initiated, false, Outcome::PublicationFailed];
    }

    #[DataProvider('auditFailures')]
    public function testAuditFailure(Outcome $outcome, int $compensations): void
    {
        $port = $this->createMock(OrganizationDeletionInitiationPort::class);
        $port->expects(self::once())->method('deleteOrganizationWithOutcome')->willReturn(new Result($outcome, 'job', false, 'run'));
        $port->expects(self::once())->method('recordAudit')->willThrowException(new RuntimeException('audit failed'));
        $port->expects(self::exactly($compensations))->method('revertInitiation')->with('org', false, 'job', 'run')->willReturn(true);
        $port->expects(self::never())->method('publishInitiationAfterAudit');
        $this->expectExceptionMessage('audit failed');
        new OrganizationDeletionInitiator($port, new NullLogger())->initiate(new OrganizationDeletionContext('org'));
    }

    /** @return iterable<string, array{Outcome, int}> */
    public static function auditFailures(): iterable
    {
        yield 'new initiation compensated' => [Outcome::Initiated, 1];

        yield 'repeat never compensated' => [Outcome::AlreadyDeleting, 0];
    }

    public function testMissingOrganizationDoesNotAuditOrPublish(): void
    {
        $port = $this->createMock(OrganizationDeletionInitiationPort::class);
        $port->expects(self::once())->method('deleteOrganizationWithOutcome')->willReturn(new Result(Outcome::NotFound));
        $port->expects(self::never())->method('recordAudit');
        $port->expects(self::never())->method('publishInitiationAfterAudit');
        self::assertSame(Outcome::NotFound, new OrganizationDeletionInitiator($port, new NullLogger())->initiate(new OrganizationDeletionContext('org'))->outcome);
    }

    public function testInvalidOriginIsRejectedBeforeMutation(): void
    {
        $port = $this->createMock(OrganizationDeletionInitiationPort::class);
        $port->expects(self::never())->method('deleteOrganizationWithOutcome');
        self::assertSame(Outcome::Rejected, new OrganizationDeletionInitiator($port, new NullLogger())->initiate(new OrganizationDeletionContext('org', origin: 'invalid'))->outcome);
    }
}
