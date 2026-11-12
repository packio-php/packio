<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\ORMInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackTransactionInterface;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use RuntimeException;
use Throwable;

final readonly class OAuthCallbackTransaction implements OAuthCallbackTransactionInterface
{
    public function __construct(private ORMInterface $orm) {}

    public function run(callable $callback): OAuthCallbackResult
    {
        $database = $this->orm->getSource(User::class)->getDatabase();
        if ($database->getDriver(DatabaseInterface::WRITE) !== $this->orm->getSource(UserOAuthAccount::class)->getDatabase()->getDriver(DatabaseInterface::WRITE)) {
            throw new RuntimeException('OAuth callback requires a shared User and OAuth account write connection.');
        }

        try {
            return $database->transaction($callback);
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();

            throw $exception;
        }
    }
}
