<?php

declare(strict_types=1);

namespace PackioTest\Unit\Webhook\Infrastructure\Adapter;

use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Application\Dto\SourceData;
use Packio\Webhook\Infrastructure\Adapter\SourceApiFacadeAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceApiFacadeAdapter::class)]
final class SourceApiFacadeAdapterTest extends TestCase
{
    #[Test]
    public function itMapsSourceDtoToSourceData(): void
    {
        $facade = $this->createStub(SourceApiFacadeInterface::class);
        $facade->method('findSource')->willReturn($this->dto([
            'webhook_secret' => 's3cret',
        ]));

        $source = new SourceApiFacadeAdapter($facade)->findSource('source-1');

        self::assertEquals(
            new SourceData('source-1', 'org-1', 'gitlab_self_hosted', 'https://gitlab.test/acme/repo', true, 's3cret'),
            $source,
        );
    }

    #[Test]
    public function itMapsMissingWebhookSecretToEmptyString(): void
    {
        $facade = $this->createStub(SourceApiFacadeInterface::class);
        $facade->method('findSource')->willReturn($this->dto([]));

        self::assertSame('', new SourceApiFacadeAdapter($facade)->findSource('source-1')?->webhookSecret);
    }

    #[Test]
    public function itReturnsNullForUnknownSource(): void
    {
        $facade = $this->createStub(SourceApiFacadeInterface::class);
        $facade->method('findSource')->willReturn(null);

        self::assertNull(new SourceApiFacadeAdapter($facade)->findSource('missing'));
    }

    #[Test]
    public function itSchedulesSyncWithPriorityFive(): void
    {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method('scheduleSourceSyncOnce')->with('source-1', 'org-1', 'key-1', 5)->willReturn('job-1');

        $sourceData = new SourceData('source-1', 'org-1', 'github', 'https://example.test', true, '');

        self::assertSame('job-1', new SourceApiFacadeAdapter($facade)->scheduleSourceSyncOnce($sourceData, 'key-1'));
    }

    /** @param array<string, mixed> $providerConfig */
    private function dto(array $providerConfig): SourceDto
    {
        return new SourceDto(
            id: 'source-1',
            provider: 'gitlab_self_hosted',
            type: 'repository',
            displayName: 'Repo',
            canonicalUrl: 'https://gitlab.test/acme/repo',
            url: 'https://gitlab.test/acme/repo',
            owner: 'acme',
            repository: 'repo',
            defaultRef: 'main',
            organizationId: 'org-1',
            providerConfig: $providerConfig,
        );
    }
}
