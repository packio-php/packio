<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\Cli;

use Common\Infrastructure\Cli\ObjectMapperWarmupCommand;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Laminas\ServiceManager\ServiceManager;
use Packio\Source\Api\Dto\WebhookRegistrationResultDto;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function glob;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(ObjectMapperWarmupCommand::class)]
final class ObjectMapperWarmupCommandTest extends TestCase
{
    private string $cacheDirectory;

    protected function tearDown(): void
    {
        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([
            ...(glob($formatCacheDirectory . '/*') ?: []),
            ...(glob($formatCacheDirectory . '/.*') ?: []),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
    }

    #[Test]
    public function itWarmsRegisteredMappings(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-object-mapper-' . bin2hex(random_bytes(8));

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config' => [
                    'object_mapper' => [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'mappings'           => [
                            WebhookRegistrationResult::class . '->' . WebhookRegistrationResultDto::class => [
                                'source'         => WebhookRegistrationResult::class,
                                'target'         => WebhookRegistrationResultDto::class,
                                'rules'          => [],
                                'ignored_source' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $commandTester = new CommandTester(new ObjectMapperWarmupCommand($objectMapper, new NullLogger()));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Warmed 1 object mapper(s).', $commandTester->getDisplay());
    }
}
