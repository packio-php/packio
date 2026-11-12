<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker;

use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Api\Job\JobResult;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Factory\JobProcessorFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

use function in_array;

#[CoversClass(JobProcessorFactory::class)]
#[AllowMockObjectsWithoutExpectations]
final class JobProcessorFactoryTest extends TestCase
{
    #[Test]
    public function factoryCreatesProcessorAndCallsContainer(): void
    {
        $repository    = $this->createMock(JobRepositoryInterface::class);
        $logRepository = $this->createMock(JobLogRepositoryInterface::class);

        $handler = new class implements JobHandlerInterface {
            public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult
            {
                return JobResult::success();
            }
        };

        $config = [
            'worker' => [
                'job_handlers' => [
                    'test.type'    => 'test.handler.service',
                    'another.type' => 'another.handler.service',
                ],
            ],
        ];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $id) => match ($id) {
                JobRepositoryInterface::class                     => $repository,
                JobLogRepositoryInterface::class                  => $logRepository,
                'config'                                          => $config,
                'test.handler.service', 'another.handler.service' => $handler,
                default                                           => throw new RuntimeException("Unexpected service: {$id}"),
            })
        ;

        $container->method('has')
            ->willReturnCallback(fn (string $id): bool => in_array($id, [
                'config',
                JobRepositoryInterface::class,
                JobLogRepositoryInterface::class,
                'test.handler.service',
                'another.handler.service',
            ], true))
        ;

        $jobProcessorFactory = new JobProcessorFactory();
        $jobProcessor        = $jobProcessorFactory($container);

        self::assertInstanceOf(JobProcessor::class, $jobProcessor);
    }

    #[Test]
    public function factoryThrowsOnInvalidHandler(): void
    {
        $repository    = $this->createMock(JobRepositoryInterface::class);
        $logRepository = $this->createMock(JobLogRepositoryInterface::class);

        $config = [
            'worker' => [
                'job_handlers' => [
                    'bad.type' => 'invalid.handler',
                ],
            ],
        ];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $id) => match ($id) {
                JobRepositoryInterface::class    => $repository,
                JobLogRepositoryInterface::class => $logRepository,
                'config'                         => $config,
                'invalid.handler'                => new stdClass(),
                default                          => throw new RuntimeException("Unexpected service: {$id}"),
            })
        ;

        $container->method('has')
            ->willReturnCallback(fn (string $id): bool => in_array($id, [
                'config',
                JobRepositoryInterface::class,
                JobLogRepositoryInterface::class,
                'invalid.handler',
            ], true))
        ;

        $jobProcessorFactory = new JobProcessorFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement');

        $jobProcessorFactory($container);
    }

    #[Test]
    public function factoryWorksWithoutWorkerConfig(): void
    {
        $repository    = $this->createMock(JobRepositoryInterface::class);
        $logRepository = $this->createMock(JobLogRepositoryInterface::class);

        $config = [];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $id) => match ($id) {
                JobRepositoryInterface::class    => $repository,
                JobLogRepositoryInterface::class => $logRepository,
                'config'                         => $config,
                default                          => throw new RuntimeException("Unexpected service: {$id}"),
            })
        ;

        $container->method('has')
            ->willReturnCallback(fn (string $id): bool => in_array($id, ['config', JobRepositoryInterface::class, JobLogRepositoryInterface::class], true))
        ;

        $jobProcessorFactory = new JobProcessorFactory();
        $jobProcessor        = $jobProcessorFactory($container);

        self::assertInstanceOf(JobProcessor::class, $jobProcessor);
    }

    #[Test]
    public function factoryWorksWithoutConfigService(): void
    {
        $repository    = $this->createMock(JobRepositoryInterface::class);
        $logRepository = $this->createMock(JobLogRepositoryInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(fn (string $id) => match ($id) {
                JobRepositoryInterface::class    => $repository,
                JobLogRepositoryInterface::class => $logRepository,
                default                          => throw new RuntimeException("Unexpected service: {$id}"),
            })
        ;

        $container->method('has')->willReturn(false);

        $jobProcessorFactory = new JobProcessorFactory();
        $jobProcessor        = $jobProcessorFactory($container);

        self::assertInstanceOf(JobProcessor::class, $jobProcessor);
    }
}
