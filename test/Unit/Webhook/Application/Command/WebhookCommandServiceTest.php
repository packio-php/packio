<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Application\Command;

use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

final class WebhookCommandServiceTest extends TestCase
{
    #[Test]
    public function receiveDeliveryReturnsConcurrentlySavedDeliveryOnUniqueConflict(): void
    {
        $existing = WebhookEvent::receive('source', 'github', 'delivery', 'push', 'hash');
        $existing->markProcessed('Already processed.');
        $repository = $this->createStub(WebhookEventRepositoryInterface::class);
        $repository->method('save')->willThrowException(new RepositoryPersistException('duplicate key value violates unique constraint'));
        $repository->method('findBySourceIdAndDeliveryId')->willReturn($existing);

        $webhookEvent = new WebhookCommandService($repository)->receiveDelivery('source', 'github', 'delivery', 'push', 'hash', true);

        self::assertSame($existing, $webhookEvent);
    }

    #[Test]
    public function receiveDeliveryRethrowsPersistFailureWhenNoDeliveryExists(): void
    {
        $repository = $this->createStub(WebhookEventRepositoryInterface::class);
        $repository->method('save')->willThrowException(new RepositoryPersistException('connection lost'));
        $repository->method('findBySourceIdAndDeliveryId')->willReturn(null);

        $this->expectException(RepositoryPersistException::class);

        new WebhookCommandService($repository)->receiveDelivery('source', 'github', 'delivery', 'push', 'hash', true);
    }
}
