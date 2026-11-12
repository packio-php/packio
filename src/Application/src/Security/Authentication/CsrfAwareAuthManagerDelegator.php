<?php

declare(strict_types=1);

namespace Packio\Application\Security\Authentication;

use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use LogicException;
use Packio\Application\Security\Csrf\CsrfTokenManager;
use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

use function sprintf;

final readonly class CsrfAwareAuthManagerDelegator implements DelegatorFactoryInterface
{
    /** @param null|array<mixed> $options */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null,
    ): AuthManagerInterface {
        $inner = $callback();
        if (! $inner instanceof AuthManagerInterface) {
            throw new LogicException(sprintf('Expected %s to resolve to %s.', $name, AuthManagerInterface::class));
        }

        return new CsrfAwareAuthManager($inner, $container->get(CsrfTokenManager::class));
    }
}
