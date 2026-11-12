<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Command;

use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Application\Command\RegistryTokenCommandService;
use Packio\Identity\Application\Validator\PasswordValidator;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

#[CoversClass(IdentityCommandService::class)]
final class IdentityCommandServiceTest extends TestCase
{
    #[Test]
    public function registerUserReturnsNullWhenAConcurrentInsertTookTheEmail(): void
    {
        $user = User::register('dup@example.test', 'Other', 'hash', ['user']);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::exactly(2))->method('findByEmail')->willReturnOnConsecutiveCalls(null, $user);
        $users->expects(self::once())->method('save')->willThrowException(new RepositoryPersistException('duplicate'));

        $identityCommandService = new IdentityCommandService(
            $users,
            new ReflectionClass(RegistryTokenCommandService::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserOAuthAccountRepositoryInterface::class),
            new PasswordValidator(),
        );

        self::assertNull($identityCommandService->registerUser('dup@example.test', 'Name', 'Secret#123'));
    }
}
