<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cycle\ORM\ORMInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\Dto\SourceSettingsPatch;
use Packio\Source\Api\Dto\SourceSettingsStatus;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Service\SourceSettingsService;
use Packio\Source\Infrastructure\Adapter\PackageVisibilityAdapter;
use Packio\Source\Infrastructure\Mapper\SourceDtoMapper;
use Packio\Source\Infrastructure\Persistence\Cycle\TransactionalSourceSettingsUpdater;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class TransactionalSourceSettingsUpdaterTest extends IntegrationTestCase
{
    #[Test]
    public function packageFailureRollsBackBothWritesAndAllowsRetry(): void
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $packages      = $this->getService(PackageApiFacadeInterface::class);
        $orm           = $this->getService(ORMInterface::class);
        $organization  = $organizations->registerOrganization('settings-rollback');
        $source        = $sources->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/settings-rollback',
            organizationId: $organization->id,
        ));
        $packages->upsert(new PackageDto(name: 'org/settings', type: 'library', description: '', visibility: 'public', sourceType: 'github', sourceUrl: '', createdAt: '', updatedAt: '', organizationId: $organization->id, sourceId: $source->id));
        $actorContext        = new ActorContext($organization->id, 'owner', 'owner');
        $faultyPackages      = $this->createMock(PackageApiFacadeInterface::class);
        $faultyPackages->expects(self::once())->method('updateVisibilityBySourceIdInContext')
            ->willReturnCallback(static function(ActorContext $actorContext, string $id, string $visibility) use ($packages): array {
                $packages->updateVisibilityBySourceIdInContext($actorContext, $id, $visibility);

                throw new RuntimeException('Injected package update failure');
            })
        ;
        $sourceSettingsService = new SourceSettingsService(new TransactionalSourceSettingsUpdater(
            $orm,
            $this->getService(SourceQueryService::class),
            $this->getService(SourceCommandService::class),
            $this->getService(SourceDtoMapper::class),
            new PackageVisibilityAdapter($faultyPackages),
        ));

        try {
            $sourceSettingsService->update($actorContext, $source->id, new SourceSettingsPatch('private', false, 7));
            self::fail('Expected package failure');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected package update failure', $exception->getMessage());
        }
        self::assertSame('public', $sources->findSource($source->id)->visibility);
        self::assertTrue($sources->findSource($source->id)->active);
        self::assertSame(0, $sources->findSource($source->id)->keepLastReleases);
        self::assertSame('public', $packages->findPackageInOrganization($organization->id, 'org/settings')->visibility);
        $result = $sources->updateSourceSettings($actorContext, $source->id, new SourceSettingsPatch('private'));
        self::assertSame(SourceSettingsStatus::Updated, $result->status);
        self::assertSame('private', $sources->findSource($source->id)->visibility);
        self::assertSame('private', $packages->findPackageInOrganization($organization->id, 'org/settings')->visibility);
    }

    #[Test]
    public function deniesNonOwnersAndHidesSourcesOutsideTheOrganization(): void
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $organization  = $organizations->registerOrganization('settings-owner');
        $other         = $organizations->registerOrganization('settings-other');
        $source        = $sources->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/settings-owner',
            organizationId: $organization->id,
        ));
        $sourceSettingsPatch = new SourceSettingsPatch('private');
        self::assertSame(SourceSettingsStatus::Forbidden, $sources->updateSourceSettings(new ActorContext($organization->id, 'member', 'member'), $source->id, $sourceSettingsPatch)->status);
        self::assertSame(SourceSettingsStatus::NotFound, $sources->updateSourceSettings(new ActorContext($other->id, 'owner', 'owner'), $source->id, $sourceSettingsPatch)->status);
        self::assertSame('public', $sources->findSource($source->id)->visibility);
        self::assertTrue($sources->findSource($source->id)->active);
        self::assertSame(0, $sources->findSource($source->id)->keepLastReleases);
    }

    #[Test]
    public function patchReadsFreshSettingsAndCannotReactivateAnArchivedSource(): void
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $sources       = $this->getService(SourceApiFacadeInterface::class);
        $orm           = $this->getService(ORMInterface::class);
        $organization  = $organizations->registerOrganization('settings-fresh');
        $source        = $sources->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/settings-fresh',
            organizationId: $organization->id,
        ));
        $actorContext = new ActorContext($organization->id, 'owner', 'owner');
        // Simulate a committed change after the source entered the identity map.
        $storage = $orm->getSource('source');
        $storage->getDatabase()->update($storage->getTable())->values([
            'visibility'         => 'private',
            'active'             => false,
            'keep_last_releases' => 8,
        ])->where('uuid', $source->id)->run();
        $result = $sources->updateSourceSettings($actorContext, $source->id, new SourceSettingsPatch(keepLastReleases: 0));
        self::assertSame('private', $result->source->visibility);
        self::assertFalse($result->source->active);
        self::assertSame(0, $result->source->keepLastReleases);
        $sources->archiveInContext($actorContext, $source->id);
        $result = $sources->updateSourceSettings($actorContext, $source->id, new SourceSettingsPatch(active: true));
        self::assertSame(SourceSettingsStatus::Updated, $result->status);
        self::assertTrue($result->source->archived);
        self::assertFalse($result->source->active);
    }
}
