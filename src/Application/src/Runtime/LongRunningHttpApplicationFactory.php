<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use Common\Runtime\RuntimeResetterInterface;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Sirix\ObjectMapper\Contract\WarmableObjectMapperInterface;

final class LongRunningHttpApplicationFactory
{
    public function __invoke(ContainerInterface $container): LongRunningHttpApplication
    {
        /** @var Application $application */
        $application = $container->get(Application::class);

        /** @var MiddlewareFactory $middlewareFactory */
        $middlewareFactory = $container->get(MiddlewareFactory::class);

        (require 'config/pipeline.php')($application, $middlewareFactory, $container);
        (require 'config/routes.php')($application, $middlewareFactory, $container);

        /** @var RuntimeResetterInterface $runtimeResetter */
        $runtimeResetter = $container->get(RuntimeResetterInterface::class);

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);

        /** @var WarmableObjectMapperInterface $objectMapper */
        $objectMapper = $container->get(WarmableObjectMapperInterface::class);

        return new LongRunningHttpApplication($application, $runtimeResetter, $logger, $objectMapper);
    }
}
