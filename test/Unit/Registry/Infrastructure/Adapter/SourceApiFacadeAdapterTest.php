<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Adapter;

use Packio\Registry\Infrastructure\Adapter\SourceApiFacadeAdapter;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use PHPUnit\Framework\TestCase;

final class SourceApiFacadeAdapterTest extends TestCase
{
    public function testSourceDataDoesNotCarryTheWebhookSecret(): void
    {
        $sourceApiFacade = $this->createStub(SourceApiFacadeInterface::class);
        $sourceApiFacade->method('findSource')->willReturn(new SourceDto(
            id: 'source-1',
            provider: 'github',
            type: 'github',
            displayName: 'acme/demo',
            canonicalUrl: 'https://github.com/acme/demo',
            url: 'https://github.com/acme/demo',
            owner: 'acme',
            repository: 'demo',
            defaultRef: 'main',
            organizationId: 'org-1',
            providerConfig: [
                'base_url'       => 'https://github.example.com',
                'webhook_secret' => 'private-secret',
            ],
        ));

        $sourceData = new SourceApiFacadeAdapter($sourceApiFacade, $this->createStub(SourceSecretResolverInterface::class))
            ->findSource('source-1')
        ;

        self::assertSame([
            'base_url' => 'https://github.example.com',
        ], $sourceData?->providerConfig);
    }
}
