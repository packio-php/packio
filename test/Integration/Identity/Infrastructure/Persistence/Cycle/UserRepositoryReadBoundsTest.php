<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Heap\Heap;
use Cycle\ORM\ORMInterface;
use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function count;
use function sprintf;

final class UserRepositoryReadBoundsTest extends IntegrationTestCase
{
    #[Test]
    public function itUsesBoundedStableReadsAndStrictRoleAggregates(): void
    {
        $identity = $this->identity();
        $empty    = $identity->findUsersPaginated(new PaginationCriteria(page: 9), new UserListFilterDto());
        self::assertSame([], $empty->items);
        self::assertSame(0, $empty->total);
        self::assertSame(1, $empty->currentPage);
        self::assertSame(1, $empty->lastPage());

        $database = $this->database();
        for ($index = 1; $index <= 101; ++$index) {
            $this->insertUser($database, sprintf('00000000-0000-0000-0000-%012d', $index), sprintf('user-%03d@example.test', $index), '["user"]');
        }
        $earlierEmailId = '00000000-0000-0000-0000-000000000201';
        $laterEmailId   = '00000000-0000-0000-0000-000000000202';
        $this->insertUser($database, $laterEmailId, 'tie-b@example.test', '["user"]');
        $this->insertUser($database, $earlierEmailId, 'tie-a@example.test', '["user"]');
        $this->insertUser($database, '00000000-0000-0000-0000-000000000301', 'admin-array@example.test', '["admin"]');
        $this->insertUser($database, '00000000-0000-0000-0000-000000000302', 'admin-scalar@example.test', '"admin"');
        $this->insertUser($database, '00000000-0000-0000-0000-000000000303', 'admin-object@example.test', '{"admin":true}');

        $orm      = $this->orm();
        $heap     = $this->heap($orm);
        $heap->clean();

        self::assertSame(1, $identity->adminsCount());
        self::assertSame(1, $identity->activeAdminsCount());
        self::assertCount(0, $heap->getIterator());

        $database->execute("DELETE FROM users WHERE email IN ('admin-scalar@example.test', 'admin-object@example.test')");

        self::assertSame([], $identity->findUsersByIds([]));
        self::assertCount(0, $heap->getIterator());

        $paginatedResultDto = $identity->findUsersPaginated(
            PaginationCriteria::fromArray([
                'page'     => (string) PHP_INT_MAX,
                'per_page' => '100',
            ]),
            UserListFilterDto::fromArray([]),
        );
        self::assertSame(1, $paginatedResultDto->currentPage);
        self::assertLessThanOrEqual(100, count($paginatedResultDto->items));
        self::assertCount(100, $heap->getIterator());

        $taggedUsers = $identity->findUsersPaginated(
            new PaginationCriteria(page: 1, perPage: 20, search: 'tie-'),
            UserListFilterDto::fromArray([]),
        );
        self::assertSame([$earlierEmailId, $laterEmailId], array_map(static fn ($user): string => $user->id, $taggedUsers->items));

        $lastPage = $identity->findUsersPaginated(new PaginationCriteria(page: 999, perPage: 100), new UserListFilterDto());
        self::assertSame(2, $lastPage->currentPage);
        self::assertCount(4, $lastPage->items);

        $noMatches = $identity->findUsersPaginated(new PaginationCriteria(page: 999, search: 'does-not-exist'), new UserListFilterDto());
        self::assertSame([], $noMatches->items);
        self::assertSame(0, $noMatches->total);
        self::assertSame(1, $noMatches->currentPage);
    }

    private function insertUser(DatabaseInterface $database, string $id, string $email, string $roles): void
    {
        $database->execute(
            'INSERT INTO users (uuid, email, display_name, password_hash, roles, created_at) VALUES (?, ?, ?, ?, ?::json, CURRENT_TIMESTAMP)',
            [$id, $email, 'User', '', $roles],
        );
    }

    private function database(): DatabaseInterface
    {
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);

        return $provider->database();
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }

    private function orm(): ORMInterface
    {
        $orm = $this->getService(ORMInterface::class);
        self::assertInstanceOf(ORMInterface::class, $orm);

        return $orm;
    }

    private function heap(ORMInterface $orm): Heap
    {
        $heap = $orm->getHeap();
        self::assertInstanceOf(Heap::class, $heap);

        return $heap;
    }
}
