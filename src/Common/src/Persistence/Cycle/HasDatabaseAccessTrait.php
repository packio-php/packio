<?php

declare(strict_types=1);

namespace Common\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;

/**
 * Provides direct access to the Cycle database of the owning entity for raw
 * queries and transactions.
 *
 * The using repository must declare:
 * - private const ENTITY_CLASS with the managed entity class name;
 * - the protected $orm property (provided by AbstractWriteRepository).
 */
trait HasDatabaseAccessTrait
{
    private function database(): DatabaseInterface
    {
        return $this->orm->getSource(self::ENTITY_CLASS)->getDatabase();
    }
}
