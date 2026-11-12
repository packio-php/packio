<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Adapter\Worker;

use Cake\Chronos\Chronos;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\ArchiveReadStream;
use Packio\Registry\Application\Storage\PackageArchiveStorageInterface;
use Packio\Registry\Application\Storage\StoredArchive;
use Packio\Registry\Infrastructure\Adapter\Worker\CleanupPackageArchivesJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function in_array;

#[CoversClass(CleanupPackageArchivesJobHandler::class)]
final class CleanupPackageArchivesJobHandlerTest extends TestCase
{
    #[Test]
    public function itFailsWhenArchiveKeysCannotBeDeleted(): void
    {
        $cleanupJobPackageArchiveStorageStub = new CleanupJobPackageArchiveStorageStub(
            failingKeys: ['private/org-1/dist/vendor/package/1.1.0_ref.zip'],
        );
        $cleanupJobLoggerStub = new CleanupJobLoggerStub();

        $jobResult = new CleanupPackageArchivesJobHandler($cleanupJobPackageArchiveStorageStub)->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
            'storage_keys'    => [
                'private/org-1/dist/vendor/package/1.0.0_ref.zip',
                'private/org-1/dist/vendor/package/1.1.0_ref.zip',
            ],
        ], $cleanupJobLoggerStub);

        self::assertFalse($jobResult->isSuccess());
        self::assertSame('Archive object cleanup failed.', $jobResult->getErrorMessage());
        self::assertSame([
            'deleted' => 1,
            'failed'  => 1,
        ], $jobResult->getData());
        self::assertSame([
            'private/org-1/dist/vendor/package/1.0.0_ref.zip',
            'private/org-1/dist/vendor/package/1.1.0_ref.zip',
        ], $cleanupJobPackageArchiveStorageStub->deletedKeys);
        self::assertSame(['Archive object cleanup failed.'], $cleanupJobLoggerStub->warnings);
    }
}

final class CleanupJobPackageArchiveStorageStub implements PackageArchiveStorageInterface
{
    /** @var list<string> */
    public array $deletedKeys = [];

    /**
     * @param list<string> $failingKeys
     */
    public function __construct(private readonly array $failingKeys = []) {}

    public function exists(string $key): bool
    {
        unset($key);

        return true;
    }

    public function list(string $prefix): iterable
    {
        unset($prefix);

        return [];
    }

    public function put(string $key, mixed $body, ArchiveObjectMetadata $archiveObjectMetadata): StoredArchive
    {
        unset($body);

        return new StoredArchive(
            key: $key,
            bucket: null,
            size: 0,
            sha256: '',
            etag: null,
            contentType: $archiveObjectMetadata->contentType,
            storedAt: new Chronos(),
        );
    }

    public function read(string $key): ArchiveReadStream
    {
        unset($key);

        throw new RuntimeException('Not implemented.');
    }

    public function delete(string $key): void
    {
        $this->deletedKeys[] = $key;

        if (in_array($key, $this->failingKeys, true)) {
            throw new RuntimeException('Delete failed.');
        }
    }
}

final class CleanupJobLoggerStub implements JobLoggerInterface
{
    /** @var list<string> */
    public array $warnings = [];

    public function debug(string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        unset($context);

        $this->warnings[] = $message;
    }

    public function error(string $message, array $context = []): void
    {
        unset($message, $context);
    }
}
