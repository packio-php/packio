<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Middleware;

use Closure;
use Laminas\Diactoros\Response\EmptyResponse;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Sirix\InertiaPsr15\Model\OnceProp;
use Sirix\InertiaPsr15\Service\InertiaFlashStateInterface;
use Sirix\InertiaPsr15\Service\InertiaInterface;

use function is_array;

final class TestInertia implements InertiaInterface, InertiaFlashStateInterface
{
    /** @var array<string, mixed> */
    public array $flash = [];

    /** @var null|Closure(): array<string, mixed> */
    private ?Closure $flashResolver = null;

    private bool $hasRenderedPage = false;

    public function render(string $component, array $props = []): ResponseInterface
    {
        $this->hasRenderedPage = true;

        return new EmptyResponse();
    }

    public function share(string $key, mixed $value = null): void {}

    public function shareOnce(string $key, mixed $value): OnceProp
    {
        throw new LogicException('Not used.');
    }

    public function flash(array|string $key, mixed $value = null): static
    {
        $this->flash = is_array($key) ? [...$this->flash, ...$key] : [
            ...$this->flash,
            $key => $value,
        ];

        return $this;
    }

    public function setFlashResolver(Closure $resolver): void
    {
        $this->flashResolver = $resolver;
    }

    public function pendingFlash(): array
    {
        return $this->flash;
    }

    public function consumeIncomingFlash(): void
    {
        if ($this->flashResolver instanceof Closure) {
            ($this->flashResolver)();
        }
    }

    public function hasRenderedPage(): bool
    {
        return $this->hasRenderedPage;
    }

    public function encryptHistory(bool $enabled = true): void {}

    public function clearHistory(bool $enabled = true): void {}

    public function preserveFragment(bool $enabled = true): void {}

    public function location(ResponseInterface|string $destination, int $status = 302): ResponseInterface
    {
        return new EmptyResponse($status);
    }
}
