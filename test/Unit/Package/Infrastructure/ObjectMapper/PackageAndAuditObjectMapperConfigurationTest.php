<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\ObjectMapper;

use Cake\Chronos\Chronos;
use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\ChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\NullableChronosToAtomStringTransformer;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Cycle\ORM\EntityProxyInterface;
use Laminas\ServiceManager\ServiceManager;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Audit\Domain\Entity\AuditEntry;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Domain\Entity\Package;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\ObjectMapper\Exception\MappingNotRegistered;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

use function bin2hex;
use function dirname;
use function glob;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

final class PackageAndAuditObjectMapperConfigurationTest extends TestCase
{
    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory
            || ! str_starts_with($this->cacheDirectory, sys_get_temp_dir() . '/packio-package-audit-object-mapper-')
            || ! is_dir($this->cacheDirectory)) {
            return;
        }

        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([...(glob($formatCacheDirectory . '/*') ?: []), ...(glob($formatCacheDirectory . '/.*') ?: [])] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        rmdir($this->cacheDirectory);
    }

    #[Test]
    public function itMapsPackageAndAuditEntitiesWithTheEstablishedPublicContract(): void
    {
        $objectMapper      = $this->objectMapper();
        $package           = Package::create('organization-1', 'packio', 'library', 'A package.', 'public', 'source-1', 'github', 'https://example.test/packio');
        $package->setCreatedAt(new Chronos('2026-08-30T10:00:00+00:00'));

        $packageDto = $objectMapper->map($package, PackageDto::class);

        self::assertSame('packio', $packageDto->name);
        self::assertSame('2026-08-30T10:00:00+00:00', $packageDto->createdAt);
        self::assertSame('', $packageDto->updatedAt);
        self::assertSame('organization-1', $packageDto->organizationId);
        self::assertSame('source-1', $packageDto->sourceId);

        $auditEntry = AuditEntry::record('package.created', [
            'package' => 'packio',
        ], actorId: 'user-1', subjectType: 'package', subjectId: 'packio');
        $auditEntry->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        $auditEntryDto = $objectMapper->map($auditEntry, AuditEntryDto::class);

        self::assertSame('00000000-0000-0000-0000-000000000001', $auditEntryDto->id);
        self::assertSame('package.created', $auditEntryDto->event);
        self::assertSame($auditEntry->occurredAt()->format(DATE_ATOM), $auditEntryDto->occurredAt);
        self::assertSame([
            'package' => 'packio',
        ], $auditEntryDto->context);
        self::assertSame('user-1', $auditEntryDto->actorId);
        self::assertSame('package', $auditEntryDto->subjectType);
        self::assertSame('packio', $auditEntryDto->subjectId);
    }

    #[Test]
    public function itAcceptsOnlyDirectCycleProxiesForPackageAndAuditMappings(): void
    {
        $objectMapper      = $this->objectMapper();
        $packageCycleProxy = new PackageCycleProxy(
            organizationId: 'organization-1',
            name: 'packio',
            sourceId: 'source-1',
        );
        $packageCycleProxy->setCreatedAt(new Chronos('2026-08-30T10:00:00+00:00'));
        $auditEntryCycleProxy = new AuditEntryCycleProxy('package.created', new Chronos('2026-08-30T10:00:00+00:00'));
        $auditEntryCycleProxy->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000002'));

        self::assertSame('packio', $objectMapper->map($packageCycleProxy, PackageDto::class)->name);
        self::assertSame('00000000-0000-0000-0000-000000000002', $objectMapper->map($auditEntryCycleProxy, AuditEntryDto::class)->id);

        $this->assertMappingIsRejected($objectMapper, new PackageSubclass(), PackageDto::class);
        $this->assertMappingIsRejected($objectMapper, new AuditEntrySubclass(), AuditEntryDto::class);
    }

    /** @param class-string $target */
    private function assertMappingIsRejected(ObjectMapper $objectMapper, object $source, string $target): void
    {
        try {
            $objectMapper->map($source, $target);
            self::fail('Expected a non-Cycle subclass to be rejected.');
        } catch (MappingNotRegistered) {
            self::addToAssertionCount(1);
        }
    }

    private function objectMapper(): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-package-audit-object-mapper-' . bin2hex(random_bytes(8));
        $packageConfig        = require dirname(__DIR__, 5) . '/src/Module/Package/config/object-mapper.config.php';
        $auditConfig          = require dirname(__DIR__, 5) . '/src/Module/Audit/config/object-mapper.config.php';

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                      => [
                    'object_mapper' => [
                        'mappings'           => [
                            ...$packageConfig['object_mapper']['mappings'],
                            ...$auditConfig['object_mapper']['mappings'],
                        ],
                        'cache_dir'          => $this->cacheDirectory,
                        'generate_on_demand' => false,
                        'transformers'       => [
                            ChronosToAtomStringTransformer::class,
                            NullableChronosToAtomStringTransformer::class,
                            UuidInterfaceToStringTransformer::class,
                        ],
                    ],
                ],
                ChronosToAtomStringTransformer::class         => new ChronosToAtomStringTransformer(),
                NullableChronosToAtomStringTransformer::class => new NullableChronosToAtomStringTransformer(),
                UuidInterfaceToStringTransformer::class       => new UuidInterfaceToStringTransformer(),
            ],
        ]));
        $objectMapper->warmup();

        return $objectMapper;
    }
}

final class PackageCycleProxy extends Package implements EntityProxyInterface {}

final class AuditEntryCycleProxy extends AuditEntry implements EntityProxyInterface {}

final class PackageSubclass extends Package {}

final class AuditEntrySubclass extends AuditEntry {}
