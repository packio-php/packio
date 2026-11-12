<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function bin2hex;
use function random_bytes;

final class UserEmailUniqueIndexTest extends IntegrationTestCase
{
    #[Test]
    public function duplicateEmailIsRejectedByTheDatabase(): void
    {
        $users = $this->getService(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $email = 'dup-' . bin2hex(random_bytes(6)) . '@example.test';
        $users->save(User::register($email, 'First', 'hash', ['user']));

        $this->expectException(RepositoryPersistException::class);
        $users->save(User::register($email, 'Second', 'hash', ['user']));
    }
}
