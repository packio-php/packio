<?php

declare(strict_types=1);

namespace Packio\Webhook\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\Injection\Fragment;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function array_fill;
use function count;
use function implode;
use function iterator_to_array;
use function max;

/**
 * @extends AbstractWriteRepository<WebhookEvent>
 */
final class WebhookEventRepository extends AbstractWriteRepository implements WebhookEventRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = WebhookEvent::class;

    public function save(WebhookEvent $webhookEvent): WebhookEvent
    {
        $this->persistAndFlush($webhookEvent);

        return $webhookEvent;
    }

    public function findBySourceIdAndDeliveryId(string $sourceId, string $deliveryId): ?WebhookEvent
    {
        $entity = $this->select()
            ->where([
                'sourceId'   => $sourceId,
                'deliveryId' => $deliveryId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof WebhookEvent ? $entity : null;
    }

    public function recentForSource(string $sourceId, int $limit = 10): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'sourceId' => $sourceId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function recentForSources(array $sourceIds, int $limit): array
    {
        if ([] === $sourceIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($sourceIds), '?'));

        return iterator_to_array(
            $this->select()
                ->where(new Fragment(
                    'uuid IN (SELECT uuid FROM (SELECT uuid, ROW_NUMBER() OVER (PARTITION BY source_id ORDER BY created_at DESC) AS rn'
                    . ' FROM webhook_events WHERE source_id IN (' . $placeholders . ')) ranked WHERE rn <= ?)',
                    ...[...$sourceIds, $limit],
                ))
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function deleteBySourceId(string $sourceId): int
    {
        return $this->database()->delete('webhook_events', [
            'source_id' => $sourceId,
        ])->run();
    }

    public function countOlderThan(Chronos $before): int
    {
        return $this->database()->select()->from('webhook_events')
            ->where('created_at', '<', $before->setTimezone('UTC')->toDateTimeString())
            ->count()
        ;
    }

    public function deleteOlderThan(Chronos $before, int $batchSize = 1000): int
    {
        $total     = 0;
        $batchSize = max(1, $batchSize);

        do {
            // Postgres has no DELETE ... LIMIT, so pick the batch by key.
            $deleted = $this->database()->delete('webhook_events')
                ->where(new Fragment(
                    'uuid IN (SELECT uuid FROM webhook_events WHERE created_at < ? ORDER BY created_at LIMIT ?)',
                    $before->setTimezone('UTC')->toDateTimeString(),
                    $batchSize,
                ))
                ->run()
            ;
            $total += $deleted;
        } while ($deleted >= $batchSize);

        return $total;
    }
}
