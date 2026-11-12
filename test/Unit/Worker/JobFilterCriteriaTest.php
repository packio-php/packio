<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Packio\Worker\Api\Dto\JobFilterCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JobFilterCriteria::class)]
final class JobFilterCriteriaTest extends TestCase
{
    #[Test]
    public function fromArrayNormalizesListsDatesAndBooleans(): void
    {
        $jobFilterCriteria = JobFilterCriteria::fromArray([
            'organization_id' => ' org-1 ',
            'status'          => ['failed', 'invalid', 'running', 'failed'],
            'type'            => 'source.delete, organization.delete',
            'search'          => ' cleanup ',
            'deletion_run_id' => ' run-1 ',
            'created_from'    => '2026-06-01',
            'created_to'      => 'not-a-date',
            'has_warnings'    => '1',
            'has_error'       => 'false',
        ]);

        self::assertSame('org-1', $jobFilterCriteria->organizationId);
        self::assertSame(['failed', 'running'], $jobFilterCriteria->statuses);
        self::assertSame(['source.delete', 'organization.delete'], $jobFilterCriteria->types);
        self::assertSame('cleanup', $jobFilterCriteria->search);
        self::assertSame('run-1', $jobFilterCriteria->deletionRunId);
        self::assertSame('2026-06-01', $jobFilterCriteria->createdFrom?->format('Y-m-d'));
        self::assertNull($jobFilterCriteria->createdTo);
        self::assertTrue($jobFilterCriteria->hasWarnings);
        self::assertFalse($jobFilterCriteria->hasError);
    }
}
