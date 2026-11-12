<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\ORMInterface;
use Packio\Package\Application\Command\DownloadCountTransactionInterface;
use RuntimeException;
use Throwable;

final readonly class DownloadCountTransaction implements DownloadCountTransactionInterface
{
    public function __construct(private ORMInterface $orm) {}

    public function run(callable $callback): bool
    {
        $database = $this->orm->getSource('package')->getDatabase();
        if ($database->getDriver(DatabaseInterface::WRITE) !== $this->orm->getSource('release')->getDatabase()->getDriver(DatabaseInterface::WRITE)) {
            throw new RuntimeException('Download counting requires a shared Package and Release write connection.');
        }

        try {
            return $database->transaction($callback);
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();

            throw $exception;
        }
    }
}
