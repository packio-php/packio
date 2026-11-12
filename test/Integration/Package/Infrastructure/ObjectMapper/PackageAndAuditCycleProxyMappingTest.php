<?php

declare(strict_types=1);

namespace PackioTest\Integration\Package\Infrastructure\ObjectMapper;

use Cycle\ORM\EntityProxyInterface;
use Cycle\ORM\ORMInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Audit\Infrastructure\Persistence\Cycle\AuditRepository;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Infrastructure\Persistence\Cycle\PackageRepository;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function bin2hex;
use function get_parent_class;
use function random_bytes;

final class PackageAndAuditCycleProxyMappingTest extends IntegrationTestCase
{
    #[Test]
    public function itMapsPersistedAndReloadedPackageAndAuditCycleProxies(): void
    {
        $suffix         = bin2hex(random_bytes(6));
        $organizationId = 'organization-' . $suffix;
        $packageName    = 'package-' . $suffix;
        $package        = Package::create(
            organizationId: $organizationId,
            name: $packageName,
            type: 'library',
            description: 'Public package description.',
            visibility: 'public',
            sourceId: 'source-' . $suffix,
            sourceType: 'github',
            sourceUrl: 'https://example.test/' . $packageName,
            readmeHtml: '<p>Readme</p>',
        );
        $this->packageRepository()->upsertByNaturalKey($package);

        $auditEntry = AuditEntry::record(
            event: 'package.created',
            context: [
                'kind' => 'package',
            ],
            organizationId: $organizationId,
            actorId: 'user-' . $suffix,
            subjectType: 'package',
            subjectId: $packageName,
        );
        $this->auditRepository()->save($auditEntry);

        $this->orm()->getHeap()->clean();

        $reloadedPackage = $this->packageRepository()->findByName($packageName, $organizationId);
        self::assertInstanceOf(Package::class, $reloadedPackage);
        $this->assertDirectCycleProxy($reloadedPackage, Package::class);

        $reloadedAuditEntries = $this->auditRepository()->recentByOrganization($organizationId);
        self::assertCount(1, $reloadedAuditEntries);
        $reloadedAuditEntry = $reloadedAuditEntries[0];
        self::assertInstanceOf(AuditEntry::class, $reloadedAuditEntry);
        $this->assertDirectCycleProxy($reloadedAuditEntry, AuditEntry::class);

        $packageDto = $this->objectMapper()->map($reloadedPackage, PackageDto::class);
        self::assertSame($packageName, $packageDto->name);
        self::assertSame('library', $packageDto->type);
        self::assertSame('Public package description.', $packageDto->description);
        self::assertSame('public', $packageDto->visibility);
        self::assertSame('github', $packageDto->sourceType);
        self::assertSame('https://example.test/' . $packageName, $packageDto->sourceUrl);
        self::assertSame($organizationId, $packageDto->organizationId);
        self::assertTrue($packageDto->active);
        self::assertSame('<p>Readme</p>', $packageDto->readmeHtml);
        self::assertSame('source-' . $suffix, $packageDto->sourceId);
        self::assertSame(0, $packageDto->downloadCount);

        $auditEntryDto = $this->objectMapper()->map($reloadedAuditEntry, AuditEntryDto::class);
        self::assertSame($reloadedAuditEntry->getIdentifier()->toString(), $auditEntryDto->id);
        self::assertSame('package.created', $auditEntryDto->event);
        self::assertSame($reloadedAuditEntry->occurredAt()->format(DATE_ATOM), $auditEntryDto->occurredAt);
        self::assertSame([
            'kind' => 'package',
        ], $auditEntryDto->context);
        self::assertSame('user-' . $suffix, $auditEntryDto->actorId);
        self::assertSame('package', $auditEntryDto->subjectType);
        self::assertSame($packageName, $auditEntryDto->subjectId);
    }

    private function assertDirectCycleProxy(object $entity, string $entityClass): void
    {
        self::assertInstanceOf(EntityProxyInterface::class, $entity);
        self::assertNotSame($entityClass, $entity::class);
        self::assertSame($entityClass, get_parent_class($entity));
    }

    private function packageRepository(): PackageRepository
    {
        $repository = $this->getService(PackageRepository::class);
        if (! $repository instanceof PackageRepository) {
            throw new RuntimeException('Package repository is not configured.');
        }

        return $repository;
    }

    private function auditRepository(): AuditRepository
    {
        $repository = $this->getService(AuditRepository::class);
        if (! $repository instanceof AuditRepository) {
            throw new RuntimeException('Audit repository is not configured.');
        }

        return $repository;
    }

    private function orm(): ORMInterface
    {
        $orm = $this->getService(ORMInterface::class);
        if (! $orm instanceof ORMInterface) {
            throw new RuntimeException('Cycle ORM is not configured.');
        }

        return $orm;
    }

    private function objectMapper(): ObjectMapperInterface
    {
        $objectMapper = $this->getService(ObjectMapperInterface::class);
        if (! $objectMapper instanceof ObjectMapperInterface) {
            throw new RuntimeException('Object mapper is not configured.');
        }

        return $objectMapper;
    }
}
