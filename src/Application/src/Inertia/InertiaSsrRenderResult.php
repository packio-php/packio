<?php

declare(strict_types=1);

namespace Packio\Application\Inertia;

final readonly class InertiaSsrRenderResult
{
    /**
     * @param list<string> $head
     */
    public function __construct(public array $head, public string $body) {}

    /**
     * @return array{head: list<string>, body: string}
     */
    public function toArray(): array
    {
        return [
            'head' => $this->head,
            'body' => $this->body,
        ];
    }
}
