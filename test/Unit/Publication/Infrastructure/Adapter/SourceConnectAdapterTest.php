<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Adapter;

use Packio\Publication\Application\Command\ConnectSourcesCommand;
use Packio\Publication\Infrastructure\Adapter\SourceConnectAdapter;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class SourceConnectAdapterTest extends TestCase
{
    public function testDelegatesAuthorizationLookupToSourceFacade(): void
    {
        $actorContext = new ActorContext('org', 'user', 'owner');
        $sources      = $this->createMock(SourceApiFacadeInterface::class);
        $sources->expects(self::once())->method('resolveConnectAuthorizationInContext')->with($actorContext, 'github', 'auth-1', [
            'token' => 'secret',
        ], 'instance-1')->willReturn('resolved-auth');
        $sourceConnectAdapter = new SourceConnectAdapter($sources, $this->createStub(JobApiFacadeInterface::class));
        self::assertSame('resolved-auth', $sourceConnectAdapter->authorize(new ConnectSourcesCommand($actorContext, 'github', ['a/b'], authorizationId: 'auth-1', authorizationPayload: [
            'token' => 'secret',
        ], providerInstanceId: 'instance-1')));
    }
}
