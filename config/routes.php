<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Routes are now defined via attributes on handlers.
 * See: #[Get], #[Post], #[Put], #[Patch], #[Delete], #[Any] attributes.
 *
 * @see src/Module/'*'/src/Infrastructure/Handler/
 */
return static function(Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {};
