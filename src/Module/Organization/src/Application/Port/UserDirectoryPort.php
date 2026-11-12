<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface UserDirectoryPort
{
    /**
     * @param list<string> $ids
     *
     * @return array<string, array{displayName: string, email: string}>
     */
    public function findByIds(array $ids): array;
}
