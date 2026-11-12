<?php

declare(strict_types=1);

namespace PackioTest\Integration\Package\Application\Command;

use Cycle\ORM\ORMInterface;
use Packio\Package\Application\Command\PackageCommandService;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function bin2hex;
use function random_bytes;

final class PackageDownloadCountTest extends IntegrationTestCase
{
    #[Test]
    public function twoIncrementsThroughDifferentEntityInstancesAddTwo(): void
    {
        $organizationId = $this->seed();
        $this->packages()->findByName('acme/demo', $organizationId);

        self::assertTrue($this->service()->incrementDownloadCount($organizationId, 'acme/demo', '1.0.0'));
        self::assertTrue($this->service()->incrementDownloadCount($organizationId, 'acme/demo', '1.0.0'));

        $this->orm()->getHeap()->clean();
        self::assertSame(2, $this->packages()->findByName('acme/demo', $organizationId)?->downloadCount());
        self::assertSame(2, $this->releases()->findByPackageAndVersion('acme/demo', '1.0.0', $organizationId)?->downloadCount());
    }

    #[Test]
    public function staleEntitySaveKeepsIncrements(): void
    {
        $organizationId = $this->seed();
        $stale          = $this->packages()->findByName('acme/demo', $organizationId);
        self::assertInstanceOf(Package::class, $stale);

        $this->service()->incrementDownloadCount($organizationId, 'acme/demo', '1.0.0');
        $this->service()->incrementDownloadCount($organizationId, 'acme/demo', '1.0.0');
        $stale->updateReadmeHtml('<p>new</p>');
        $this->packages()->save($stale);

        $this->orm()->getHeap()->clean();
        $stored = $this->packages()->findByName('acme/demo', $organizationId);
        self::assertSame(2, $stored?->downloadCount());
        self::assertSame('<p>new</p>', $stored->readmeHtml());
    }

    #[Test]
    public function missingReleaseStillCountsPackage(): void
    {
        $organizationId = $this->seed();

        self::assertTrue($this->service()->incrementDownloadCount($organizationId, 'acme/demo', '9.9.9'));

        $this->orm()->getHeap()->clean();
        self::assertSame(1, $this->packages()->findByName('acme/demo', $organizationId)?->downloadCount());
        self::assertSame(0, $this->releases()->findByPackageAndVersion('acme/demo', '1.0.0', $organizationId)?->downloadCount());
    }

    #[Test]
    public function missingPackageReturnsFalse(): void
    {
        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $this->releases()->upsertByNaturalKey($this->release($organizationId));

        self::assertFalse($this->service()->incrementDownloadCount($organizationId, 'acme/demo', '1.0.0'));

        $this->orm()->getHeap()->clean();
        self::assertSame(0, $this->releases()->findByPackageAndVersion('acme/demo', '1.0.0', $organizationId)?->downloadCount());
    }

    private function seed(): string
    {
        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $this->packages()->upsertByNaturalKey(Package::create($organizationId, 'acme/demo', 'library', '', 'public', 'source-1', 'github', 'https://github.com/acme/demo'));
        $this->releases()->upsertByNaturalKey($this->release($organizationId));

        return $organizationId;
    }

    private function release(string $organizationId): Release
    {
        return Release::create($organizationId, 'acme/demo', '1.0.0', 'abc123', [
            'name' => 'acme/demo',
        ], [
            'type' => 'git',
        ], [
            'type' => 'zip',
        ]);
    }

    private function service(): PackageCommandService
    {
        return $this->typed(PackageCommandService::class);
    }

    private function packages(): PackageRepositoryInterface
    {
        return $this->typed(PackageRepositoryInterface::class);
    }

    private function releases(): ReleaseRepositoryInterface
    {
        return $this->typed(ReleaseRepositoryInterface::class);
    }

    private function orm(): ORMInterface
    {
        return $this->typed(ORMInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function typed(string $id): object
    {
        $service = $this->getService($id);
        if (! $service instanceof $id) {
            throw new RuntimeException($id . ' is not configured.');
        }

        return $service;
    }
}
