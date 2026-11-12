<?php

declare(strict_types=1);

namespace Common\Runtime;

use Cycle\ORM\ORMInterface;

final readonly class CycleOrmHeapResetter implements RuntimeResetterInterface
{
    public function __construct(private ORMInterface $orm) {}

    public function reset(): void
    {
        $this->orm->getHeap()->clean();
    }
}
