<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Repository;

use Packio\Source\Domain\Entity\SourceOAuthAccount;

interface SourceOAuthAccountLockingRepositoryInterface
{
    /**
     * Runs the callback while holding a database row lock for the account.
     *
     * @template T
     *
     * @param callable(SourceOAuthAccount): T $callback
     *
     * @return null|T
     */
    public function withLockedById(string $id, callable $callback): mixed;
}
