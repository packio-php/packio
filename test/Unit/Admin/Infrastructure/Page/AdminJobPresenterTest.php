<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Page;

use Packio\Admin\Infrastructure\Page\AdminJobPresenter;
use Packio\Admin\Infrastructure\Page\AdminOrganizationPresenter;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Worker\Api\Dto\JobDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminJobPresenter::class)]
#[CoversClass(AdminOrganizationPresenter::class)]
final class AdminJobPresenterTest extends TestCase
{
    public function testSharedJobContract(): void
    {
        $jobDto = new JobDto(
            'job',
            'org',
            'source.delete',
            [
                'deletion_run_id' => 'run',
            ],
            'key',
            'failed',
            2,
            'scheduled',
            'started',
            null,
            1,
            3,
            'failure',
            'details',
            'worker',
            'created',
            4,
            2
        );

        self::assertSame([
            'id'              => 'job',
            'organizationId'  => 'org',
            'type'            => 'source.delete',
            'payload'         => [
                'deletion_run_id' => 'run',
            ],
            'idempotencyKey'  => 'key',
            'status'          => 'failed',
            'priority'        => 2,
            'scheduledAt'     => 'scheduled',
            'startedAt'       => 'started',
            'completedAt'     => null,
            'attemptCount'    => 1,
            'maxAttempts'     => 3,
            'errorMessage'    => 'failure',
            'workerId'        => 'worker',
            'createdAt'       => 'created',
            'durationSeconds' => 4,
            'warningCount'    => 2,
        ], new AdminJobPresenter()->present($jobDto));
    }

    public function testOrganizationContract(): void
    {
        self::assertSame([
            'id'     => 'org',
            'slug'   => 'acme',
            'name'   => 'Acme',
            'status' => 'deleting',
        ], new AdminOrganizationPresenter()->present(new OrganizationDto('org', 'acme', 'Acme', status: 'deleting')));
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('deletionRuns')]
    public function testDeletionRunLookupPreservesDepthFirstScalarContract(array $payload, ?string $expected): void
    {
        self::assertSame($expected, new AdminJobPresenter()->deletionRunId($payload));
    }

    /** @return iterable<string, array{array<string, mixed>, ?string}> */
    public static function deletionRuns(): iterable
    {
        yield 'missing' => [[], null];

        yield 'root' => [[
            'deletion_run_id' => 'run',
        ], 'run'];

        yield 'nested arrays' => [[
            'jobs' => [[
                'payload' => [
                    'deletion_run_id' => 'nested',
                ],
            ]],
        ], 'nested'];

        yield 'first match is depth first' => [[
            'nested'          => [
                'deletion_run_id' => 'first',
            ],
            'deletion_run_id' => 'last',
        ], 'first'];

        yield 'empty is skipped' => [[
            'deletion_run_id' => '',
            'nested'          => [
                'deletion_run_id' => 'next',
            ],
        ], 'next'];

        yield 'null is skipped' => [[
            'deletion_run_id' => null,
        ], null];

        yield 'false is skipped' => [[
            'deletion_run_id' => false,
        ], null];

        yield 'integer zero retained' => [[
            'deletion_run_id' => 0,
        ], '0'];

        yield 'boolean true retained' => [[
            'deletion_run_id' => true,
        ], '1'];

        yield 'array is searched recursively' => [[
            'deletion_run_id' => [
                'deletion_run_id' => 'nested',
            ],
        ], 'nested'];
    }
}
