<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsStatus;
use Packio\Source\Application\Service\SourceSettingsService;
use Packio\Source\Application\Service\SourceSettingsUpdaterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SourceSettingsServiceTest extends TestCase
{
    #[Test]
    #[DataProvider('forbiddenContexts')]
    public function forbiddenContextDoesNotReachPersistence(ActorContext $actorContext): void
    {
        $updater = $this->createMock(SourceSettingsUpdaterInterface::class);
        $updater->expects(self::never())->method('update');
        $sourceSettingsResultDto = new SourceSettingsService($updater)->update($actorContext, 'source', new SourceSettingsPatch());
        self::assertSame(SourceSettingsStatus::Forbidden, $sourceSettingsResultDto->status);
        self::assertNull($sourceSettingsResultDto->source);
    }

    /** @return iterable<array{ActorContext}> */
    public static function forbiddenContexts(): iterable
    {
        yield [new ActorContext('organization', 'actor', 'member')];

        yield [new ActorContext('', 'actor', 'owner')];

        yield [new ActorContext(' ', 'actor', 'owner')];

        yield [new ActorContext('organization', '', 'owner')];

        yield [new ActorContext('organization', ' ', 'owner')];
    }
}
