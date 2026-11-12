<?php

declare(strict_types=1);

namespace PackioTest\Integration\Webhook\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Webhook\Infrastructure\Cli\WebhookEventsPruneCommand;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

final class WebhookEventsPruneCommandTest extends IntegrationTestCase
{
    #[Test]
    public function itDeletesOnlyEventsOlderThanTheThreshold(): void
    {
        $this->saveEvent('old', 31);
        $this->saveEvent('recent', 29);

        $output = $this->prune([]);

        self::assertStringContainsString('deleted=1', $output);
        self::assertSame(['recent'], $this->remainingDeliveryIds());
    }

    #[Test]
    public function dryRunCountsWithoutDeleting(): void
    {
        $this->saveEvent('old', 31);
        $this->saveEvent('recent', 29);

        $output = $this->prune([
            '--dry-run' => true,
        ]);

        self::assertStringContainsString('dry_run=1', $output);
        self::assertCount(2, $this->remainingDeliveryIds());
    }

    #[Test]
    public function itDeletesInBatchesUntilNothingOldIsLeft(): void
    {
        foreach (['a', 'b', 'c'] as $id) {
            $this->saveEvent($id, 40);
        }

        $repository = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $repository);

        self::assertSame(3, $repository->deleteOlderThan(Chronos::now('UTC')->subDays(30), 2));
        self::assertSame([], $this->remainingDeliveryIds());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function prune(array $input): string
    {
        $command = $this->getService(WebhookEventsPruneCommand::class);
        self::assertInstanceOf(WebhookEventsPruneCommand::class, $command);
        $commandTester = new CommandTester($command);
        self::assertSame(0, $commandTester->execute($input + [
            '--older-than-days' => '30',
        ]));

        return $commandTester->getDisplay();
    }

    private function saveEvent(string $deliveryId, int $daysAgo): void
    {
        $repository = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $repository);
        $webhookEvent = $repository->save(WebhookEvent::receive('source-prune', 'github', $deliveryId, 'push', 'hash'));

        // The create listener stamps created_at with the real clock, so set it directly.
        $this->database()->update('webhook_events')->values([
            'created_at' => Chronos::now('UTC')->subDays($daysAgo)->toDateTimeString(),
        ])->where('uuid', $webhookEvent->getIdentifier()->toString())->run();
    }

    /**
     * @return list<string>
     */
    private function remainingDeliveryIds(): array
    {
        $ids = [];
        foreach ($this->database()->select('delivery_id')->from('webhook_events')->orderBy('delivery_id')->fetchAll() as $row) {
            $ids[] = (string) $row['delivery_id'];
        }

        return $ids;
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
