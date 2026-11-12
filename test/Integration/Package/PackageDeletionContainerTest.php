<?php

declare(strict_types=1);

namespace PackioTest\Integration\Package;

use Laminas\ServiceManager\ServiceManager;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Package\Infrastructure\Adapter\Worker\PackageDeleteJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

final class PackageDeletionContainerTest extends TestCase
{
    #[Test]
    public function itResolvesTheRegisteredJobWithTheDeletionService(): void
    {
        $config     = require dirname(__DIR__, 3) . '/src/Module/Package/config/dependencies.config.php';
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::once())->method('findByName')->with('vendor/package', 'org-1')->willReturn(null);
        $dependencies             = $config['dependencies'];
        $dependencies['services'] = [
            'config'                          => $config,
            PackageRepositoryInterface::class => $repository,
            PackageArchiveCleanupPort::class  => $this->createStub(PackageArchiveCleanupPort::class),
            PackageReleaseCleanupPort::class  => $this->createStub(PackageReleaseCleanupPort::class),
        ];
        $serviceManager = new ServiceManager($dependencies);
        $handler        = $serviceManager->get($config['worker']['job_handlers'][PackageDeleteJobHandler::TYPE]);

        self::assertInstanceOf(PackageDeleteJobHandler::class, $handler);
        $result = $handler->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
        ], $this->createStub(JobLoggerInterface::class));

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->getData()['already_deleted']);
    }
}
