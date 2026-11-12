<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Mezzio\Session\SessionInterface;

use function array_key_exists;

class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private bool $changed = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $name, $default = null)
    {
        return $this->data[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function set(string $name, $value): void
    {
        $this->data[$name] = $value;
        $this->changed     = true;
    }

    public function unset(string $name): void
    {
        unset($this->data[$name]);
        $this->changed = true;
    }

    public function clear(): void
    {
        $this->data    = [];
        $this->changed = true;
    }

    public function hasChanged(): bool
    {
        return $this->changed;
    }

    public function regenerate(): SessionInterface
    {
        $this->changed = true;

        return $this;
    }

    public function isRegenerated(): bool
    {
        return false;
    }
}
