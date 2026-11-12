<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Webhook;

use Packio\Webhook\Application\Command\WebhookCommandService;
use Packio\Webhook\Application\Command\WebhookInspectionResult;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Webhook\Infrastructure\Webhook\GithubWebhookProviderHandler;
use Packio\Webhook\Infrastructure\Webhook\InvalidWebhookRequest;
use Packio\Webhook\Infrastructure\Webhook\WebhookDispatcher;
use Packio\Webhook\Infrastructure\Webhook\WebhookEnvelope;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerConfig;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerInterface;
use Packio\Webhook\Infrastructure\Webhook\WebhookProviderHandlerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function sha1;
use function str_repeat;

final class WebhookDispatcherTest extends TestCase
{
    public function testDuplicateDeliveryIsVerifiedButNotSavedAgain(): void
    {
        $webhookEvent = WebhookEvent::receive('source', 'provider', 'delivery', 'push', 'hash');
        $webhookEvent->markProcessed('Already processed.');
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $repository->expects(self::once())->method('findBySourceIdAndDeliveryId')->with('source', 'delivery')->willReturn($webhookEvent);
        $repository->expects(self::never())->method('save');
        $handler = $this->handler();
        $handler->expects(self::once())->method('validateSignature')->willReturn(null);
        $handler->expects(self::never())->method('inspectPayload');

        self::assertSame($webhookEvent, $this->dispatcher($repository, $handler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'provider', url: '', active: true, webhookSecret: ''), [], '{}'));
    }

    public function testConcurrentlySavedDeliveryIsReturnedWithoutInspection(): void
    {
        $webhookEvent = WebhookEvent::receive('source', 'provider', 'delivery', 'push', 'hash');
        $webhookEvent->markProcessed('Already processed.');
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        // First read misses; the competing request commits before our insert.
        $repository->expects(self::exactly(2))->method('findBySourceIdAndDeliveryId')->willReturnOnConsecutiveCalls(null, $webhookEvent);
        $repository->expects(self::once())->method('save')->willThrowException(new RepositoryPersistException('duplicate key value violates unique constraint'));
        $handler = $this->handler();
        $handler->expects(self::once())->method('validateSignature')->willReturn(null);
        $handler->expects(self::never())->method('inspectPayload');

        self::assertSame($webhookEvent, $this->dispatcher($repository, $handler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'provider', url: '', active: true, webhookSecret: ''), [], '{}'));
    }

    public function testInactiveSourceIsSavedOnlyAfterSignatureVerification(): void
    {
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function(WebhookEvent $webhookEvent): WebhookEvent {
            self::assertSame('ignored', $webhookEvent->status());
            self::assertSame('Source is inactive.', $webhookEvent->message());

            return $webhookEvent;
        });
        $handler = $this->handler();
        $handler->expects(self::once())->method('validateSignature')->willReturn(null);
        $handler->expects(self::never())->method('inspectPayload');

        self::assertSame('ignored', $this->dispatcher($repository, $handler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'provider', url: '', active: false, webhookSecret: ''), [], '{}')?->status());
    }

    #[DataProvider('outcomes')]
    public function testVerificationPrecedesInspectionAndNormalizedOutcomeIsPersisted(?string $signatureError, string $status): void
    {
        $saved      = null;
        $states     = [];
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        // Capture by reference: processing resolves the previously received delivery.
        $repository->method('findBySourceIdAndDeliveryId')->willReturnCallback(static function() use (&$saved): ?WebhookEvent { return $saved; });
        $repository->expects(self::exactly(2))->method('save')->willReturnCallback(static function(WebhookEvent $webhookEvent) use (&$saved, &$states): WebhookEvent {
            $saved    = $webhookEvent;
            $states[] = $webhookEvent->status();

            return $webhookEvent;
        });
        $handler = $this->handler();
        $handler->expects(self::once())->method('validateSignature')->willReturnCallback(static function() use (&$states, $signatureError): ?string {
            self::assertSame([], $states);

            return $signatureError;
        });
        $handler->expects(null === $signatureError ? self::once() : self::never())->method('inspectPayload')->willReturn(new WebhookInspectionResult($status, 'Normalized result.'));

        $event = $this->dispatcher($repository, $handler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'provider', url: '', active: true, webhookSecret: ''), [], '{}');
        self::assertSame(['received', $status], $states);
        self::assertSame($signatureError ?? 'Normalized result.', $event?->message());
        self::assertSame(sha1('{}'), $event->payloadHash());
    }

    /** @return iterable<string, array{?string, string}> */
    public static function outcomes(): iterable
    {
        yield 'processed' => [null, 'processed'];

        yield 'ignored' => [null, 'ignored'];

        yield 'invalid payload' => [null, 'rejected'];
    }

    public function testUnknownProviderDoesNotAccessPersistence(): void
    {
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $repository->expects(self::never())->method('findBySourceIdAndDeliveryId');
        $repository->expects(self::never())->method('save');
        $webhookDispatcher = new WebhookDispatcher(new WebhookCommandService($repository), new WebhookProviderHandlerRegistry([]));

        self::assertNull($webhookDispatcher->handleWebhook(new SourceData(id: '', organizationId: '', provider: 'unknown', url: '', active: false, webhookSecret: ''), [], '{}'));
        self::assertNull($webhookDispatcher->reprocessDelivery(new SourceData(id: '', organizationId: '', provider: 'unknown', url: '', active: false, webhookSecret: ''), 'delivery'));
    }

    #[DataProvider('sourceStates')]
    public function testInvalidSignatureNeverReadsOrWritesDelivery(bool $active): void
    {
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $repository->expects(self::never())->method('findBySourceIdAndDeliveryId');
        $repository->expects(self::never())->method('save');
        $handler = $this->handler();
        $handler->expects(self::once())->method('validateSignature')->willReturn('Invalid signature.');
        $handler->expects(self::never())->method('inspectPayload');
        $this->expectException(InvalidWebhookRequest::class);
        $this->dispatcher($repository, $handler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'provider', url: '', active: $active, webhookSecret: ''), [], '{}');
    }

    /** @return iterable<string, array{bool}> */
    public static function sourceStates(): iterable
    {
        yield 'active' => [true];

        yield 'inactive' => [false];
    }

    public function testOversizedDeliveryIdNeverReachesPersistence(): void
    {
        $repository = $this->createMock(WebhookEventRepositoryInterface::class);
        $repository->expects(self::never())->method('findBySourceIdAndDeliveryId');
        $repository->expects(self::never())->method('save');
        $githubWebhookProviderHandler = new GithubWebhookProviderHandler(new WebhookProviderHandlerConfig('github', ['push']));
        $this->expectException(InvalidWebhookRequest::class);
        $this->expectExceptionMessage('Webhook delivery ID or event is too long.');
        $this->dispatcher($repository, $githubWebhookProviderHandler)->handleWebhook(new SourceData(id: 'source', organizationId: '', provider: 'github', url: '', active: false, webhookSecret: ''), [
            'x-github-delivery' => str_repeat('a', 256),
        ], '{}');
    }

    private function handler(): MockObject&WebhookProviderHandlerInterface
    {
        $handler = $this->createMock(WebhookProviderHandlerInterface::class);
        $handler->method('provider')->willReturn('provider');
        $handler->method('envelope')->willReturn(new WebhookEnvelope('provider', 'delivery', 'push'));

        return $handler;
    }

    private function dispatcher(
        WebhookEventRepositoryInterface $webhookEventRepository,
        WebhookProviderHandlerInterface $webhookProviderHandler
    ): WebhookDispatcher {
        return new WebhookDispatcher(new WebhookCommandService($webhookEventRepository), new WebhookProviderHandlerRegistry([$webhookProviderHandler]));
    }
}
