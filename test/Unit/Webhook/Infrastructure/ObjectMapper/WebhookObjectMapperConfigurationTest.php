<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\ObjectMapper;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToNullableAtomStringTransformer;
use Cycle\ORM\EntityProxyInterface;
use Laminas\ServiceManager\ServiceManager;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\ObjectMapper\Exception\MappingNotRegistered;
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

final class WebhookObjectMapperConfigurationTest extends TestCase
{
    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory
            || ! str_starts_with($this->cacheDirectory, sys_get_temp_dir() . '/packio-webhook-object-mapper-')
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
    public function itMapsReceivedAndProcessedWebhookEventsWithNullableProcessedAt(): void
    {
        $webhookEvent = WebhookEvent::receive('source-1', 'github', 'delivery-1', 'push', 'payload-hash');
        $webhookEvent->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $processed = WebhookEvent::receive('source-2', 'gitlab', 'delivery-2', 'Push Hook', 'payload-hash');
        $processed->setCreatedAt(new Chronos('2026-08-30T11:12:13+00:00'));
        $processed->markSyncScheduled('job-1', 'Sync scheduled.');

        $webhookEventDto = $this->objectMapper()->map($webhookEvent, WebhookEventDto::class);
        $processedDto    = $this->objectMapper()->map($processed, WebhookEventDto::class);

        self::assertSame('source-1', $webhookEventDto->sourceId);
        self::assertSame('received', $webhookEventDto->status);
        self::assertSame('2026-08-30T10:11:12+00:00', $webhookEventDto->createdAt);
        self::assertNull($webhookEventDto->processedAt);
        self::assertNull($webhookEventDto->scheduledJobId);
        self::assertSame(0, $webhookEventDto->reprocessCount);

        self::assertSame('source-2', $processedDto->sourceId);
        self::assertSame('processed', $processedDto->status);
        self::assertSame('Sync scheduled.', $processedDto->message);
        self::assertSame('job-1', $processedDto->scheduledJobId);
        self::assertSame(0, $processedDto->reprocessCount);
        self::assertNotNull($processedDto->processedAt);
    }

    #[Test]
    public function itMapsADirectCycleProxyChild(): void
    {
        $webhookEventCycleProxy = new WebhookEventCycleProxy(
            sourceId: 'source-1',
            provider: 'github',
            deliveryId: 'delivery-1',
            event: 'push',
            payloadHash: 'payload-hash',
        );
        $webhookEventCycleProxy->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $webhookEventDto = $this->objectMapper()->map($webhookEventCycleProxy, WebhookEventDto::class);

        self::assertSame('source-1', $webhookEventDto->sourceId);
        self::assertSame('2026-08-30T10:11:12+00:00', $webhookEventDto->createdAt);
        self::assertNull($webhookEventDto->processedAt);
    }

    #[Test]
    public function itRejectsAnOrdinaryWebhookEventSubclass(): void
    {
        $webhookEventOrdinaryChild = new WebhookEventOrdinaryChild(
            sourceId: 'source-1',
            provider: 'github',
            deliveryId: 'delivery-1',
            event: 'push',
            payloadHash: 'payload-hash',
        );
        $webhookEventOrdinaryChild->setCreatedAt(new Chronos('2026-08-30T10:11:12+00:00'));

        $this->expectException(MappingNotRegistered::class);

        $this->objectMapper()->map($webhookEventOrdinaryChild, WebhookEventDto::class);
    }

    private function objectMapper(): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-webhook-object-mapper-' . bin2hex(random_bytes(8));
        $webhookConfig        = require dirname(__DIR__, 5) . '/src/Module/Webhook/config/object-mapper.config.php';

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                              => [
                    'object_mapper' => array_merge($webhookConfig['object_mapper'], [
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => [
                            ChronosToAtomStringTransformer::class,
                            NullableChronosToNullableAtomStringTransformer::class,
                        ],
                    ]),
                ],
                ChronosToAtomStringTransformer::class                 => new ChronosToAtomStringTransformer(),
                NullableChronosToNullableAtomStringTransformer::class => new NullableChronosToNullableAtomStringTransformer(),
            ],
        ]));
        $objectMapper->warmup();

        return $objectMapper;
    }
}

final class WebhookEventCycleProxy extends WebhookEvent implements EntityProxyInterface {}

final class WebhookEventOrdinaryChild extends WebhookEvent {}
