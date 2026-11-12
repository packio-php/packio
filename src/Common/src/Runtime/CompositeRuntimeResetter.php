<?php

declare(strict_types=1);

namespace Common\Runtime;

final readonly class CompositeRuntimeResetter implements RuntimeResetterInterface
{
    /**
     * @param list<RuntimeResetterInterface> $resetters
     */
    public function __construct(private array $resetters) {}

    public function reset(): void
    {
        foreach ($this->resetters as $resetter) {
            $resetter->reset();
        }
    }
}
