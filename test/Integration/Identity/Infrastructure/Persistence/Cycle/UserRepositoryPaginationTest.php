<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use InvalidArgumentException;
use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_map;
use function range;
use function sprintf;

final class UserRepositoryPaginationTest extends IntegrationTestCase
{
    #[Test]
    public function itBoundsOrdersFiltersAndCountsUsersInPostgres(): void
    {
        $identity = $this->identity();
        $commands = $this->getService(IdentityCommandService::class);
        $ids      = [];

        for ($index = 0; $index < 105; ++$index) {
            $user = $commands->createUserWithoutPassword(
                sprintf('user-%03d@example.test', 104 - $index),
                'User ' . $index,
                0 === $index ? ['admin'] : ['user'],
            );
            $ids[] = $user->getIdentifier()->toString();
        }
        $wildcard = $commands->createUserWithoutPassword('wild%_card@example.test', 'Wildcard', ['admin', 'user']);
        self::assertSame('success', $identity->banUser($wildcard->getIdentifier()->toString(), $ids[0], 'Violation of project rules.')->outcome->value);

        $paginatedResultDto = $identity->findUsersPaginated(
            new PaginationCriteria(page: 1, perPage: 100),
            UserListFilterDto::fromArray([]),
        );
        self::assertCount(100, $paginatedResultDto->items);
        self::assertSame(106, $paginatedResultDto->total);
        self::assertSame('user-000@example.test', $paginatedResultDto->items[0]->email);

        $active = $identity->findUsersPaginated(
            new PaginationCriteria(page: 1, perPage: 100),
            UserListFilterDto::fromArray([
                'status' => 'active',
            ]),
        );
        self::assertSame(105, $active->total);

        $literalSearch = $identity->findUsersPaginated(
            new PaginationCriteria(page: 1, perPage: 20, search: '%_'),
            UserListFilterDto::fromArray([
                'status' => 'banned',
            ]),
        );
        self::assertSame(1, $literalSearch->total);
        self::assertSame($wildcard->getIdentifier()->toString(), $literalSearch->items[0]->id);

        self::assertSame(106, $identity->usersCount());
        self::assertSame(2, $identity->adminsCount());
        self::assertSame(1, $identity->activeAdminsCount());

        $users = $identity->findUsersByIds([$ids[2], $ids[2], Uuid::uuid7()->toString(), $ids[1]]);
        self::assertCount(2, $users);
        self::assertSame([], $identity->findUsersByIds([]));

        $repository = $this->getService(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $repository);
        $this->expectException(InvalidArgumentException::class);
        $repository->findByIds(array_map(static fn (int $index): string => Uuid::uuid7()->toString(), range(1, 101)));
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }
}
