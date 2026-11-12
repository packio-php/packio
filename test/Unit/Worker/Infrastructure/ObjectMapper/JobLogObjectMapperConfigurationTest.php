<?php

declare(strict_types=1);

namespace PackioTest\Unit\Worker\Infrastructure\ObjectMapper;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Cycle\ORM\EntityProxyInterface;
use Laminas\ServiceManager\ServiceManager;
use Packio\Worker\Api\Dto\JobLogDto;
use Packio\Worker\Domain\Entity\JobLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

use function array_merge;
use function bin2hex;
use function dirname;
use function glob;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

final class JobLogObjectMapperConfigurationTest extends TestCase
{
    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory
            || ! str_starts_with($this->cacheDirectory, sys_get_temp_dir() . '/packio-worker-object-mapper-')
            || ! is_dir($this->cacheDirectory)) {
            return;
        }

        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([...(glob($formatCacheDirectory . '/*') ?: []), ...(glob($formatCacheDirectory . '/.*') ?: [])] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        rmdir($this->cacheDirectory);
    }

    #[Test]
    public function itMapsADirectCycleProxyJobLog(): void
    {
        $jobLogCycleProxy = new JobLogCycleProxy('job-1', JobLog::LEVEL_WARNING, 'A warning.', [
            'attempt' => 2,
        ]);
        $jobLogCycleProxy->setIdentifier(Uuid::fromString('0186d057-4a3c-7d46-b7b7-3c46e7e3c870'));
        $jobLogCycleProxy->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $jobLogDto = $this->objectMapper()->map($jobLogCycleProxy, JobLogDto::class);

        self::assertSame('0186d057-4a3c-7d46-b7b7-3c46e7e3c870', $jobLogDto->id);
        self::assertSame('job-1', $jobLogDto->jobId);
        self::assertSame(JobLog::LEVEL_WARNING, $jobLogDto->level);
        self::assertSame('A warning.', $jobLogDto->message);
        self::assertSame([
            'attempt' => 2,
        ], $jobLogDto->context);
        self::assertSame('2026-08-30T10:11:12+00:00', $jobLogDto->createdAt);
    }

    private function objectMapper(): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-worker-object-mapper-' . bin2hex(random_bytes(8));
        $workerConfig         = require dirname(__DIR__, 5) . '/src/Module/Worker/config/object-mapper.config.php';

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                     => [
                    'object_mapper' => array_merge($workerConfig['object_mapper'], [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => [
                            ChronosToAtomStringTransformer::class,
                            UuidInterfaceToStringTransformer::class,
                        ],
                    ]),
                ],
                ChronosToAtomStringTransformer::class        => new ChronosToAtomStringTransformer(),
                UuidInterfaceToStringTransformer::class      => new UuidInterfaceToStringTransformer(),
            ],
        ]));
        $objectMapper->warmup();

        return $objectMapper;
    }
}

final class JobLogCycleProxy extends JobLog implements EntityProxyInterface {}
