<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication;

use Packio\Publication\Application\Command\ConnectSourcesCommand;
use Packio\Publication\Application\Command\ConnectSourcesUseCase;
use Packio\Publication\Infrastructure\Handler\SourceConnectHandler;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;

final class ConnectSourcesTest extends IntegrationTestCase
{
    public function testBatchRemainsIdempotentAfterContainerRebuild(): void
    {
        self::assertInstanceOf(SourceConnectHandler::class, $this->getService(SourceConnectHandler::class));
        $useCase = $this->getService(ConnectSourcesUseCase::class);
        self::assertInstanceOf(ConnectSourcesUseCase::class, $useCase);
        $connectSourcesCommand = new ConnectSourcesCommand(new ActorContext('connect-test-org', 'user', 'owner'), 'generic_git', ['https://example.org/a.git', 'https://example.org/b.git']);
        $connectSourcesResult  = $useCase->execute($connectSourcesCommand);
        self::assertCount(2, $connectSourcesResult->queued);
        self::assertSame([], $connectSourcesResult->failed);
        $this->rebuildContainer();
        $useCase = $this->getService(ConnectSourcesUseCase::class);
        self::assertInstanceOf(ConnectSourcesUseCase::class, $useCase);
        $second = $useCase->execute($connectSourcesCommand);
        self::assertSame([], $second->queued);
        self::assertSame([], $second->failed);
        self::assertCount(2, $second->skipped);
        $repository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $repository);
        self::assertCount(2, $repository->findByOrganization('connect-test-org', [
            'type' => 'source.create',
        ]));
    }
}
