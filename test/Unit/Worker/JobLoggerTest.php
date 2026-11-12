<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Packio\Worker\Application\JobLogger;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JobLogger::class)]
final class JobLoggerTest extends TestCase
{
    #[Test]
    public function itRedactsSecretsBeforePersisting(): void
    {
        $jobLogger = new JobLogger('job-1', $this->createStub(JobLogRepositoryInterface::class));
        $jobLogger->warning('Clone failed', [
            'token'     => 'ghp_secret',
            'source_id' => 's-1',
        ]);

        self::assertSame([
            'token'     => '[REDACTED]',
            'source_id' => 's-1',
        ], $jobLogger->getLogs()[0]['context']);
    }

    #[Test]
    public function itRedactsCredentialsInExceptionText(): void
    {
        $jobLogger = new JobLogger('job-1', $this->createStub(JobLogRepositoryInterface::class));
        $jobLogger->error('Sync failed', [
            'error' => 'fatal: could not read from https://user:ghp_secret@github.com/acme/x.git',
        ]);

        self::assertSame(
            'fatal: could not read from https://[REDACTED]@github.com/acme/x.git',
            $jobLogger->getLogs()[0]['context']['error'],
        );
    }

    #[Test]
    public function itKeepsOrdinaryContextIntact(): void
    {
        $context   = [
            'source_id'    => 's-1',
            'package_name' => 'acme/x',
            'attempt'      => 2,
            'result'       => [
                'type'  => 'sync',
                'count' => 3,
            ],
        ];
        $jobLogger = new JobLogger('job-1', $this->createStub(JobLogRepositoryInterface::class));
        $jobLogger->info('Job completed successfully', $context);

        self::assertSame('Job completed successfully', $jobLogger->getLogs()[0]['message']);
        self::assertSame($context, $jobLogger->getLogs()[0]['context']);
    }
}
