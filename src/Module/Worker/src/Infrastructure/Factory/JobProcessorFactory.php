<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Factory;

use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;

use function sprintf;

final class JobProcessorFactory
{
    public function __invoke(ContainerInterface $container): JobProcessor
    {
        $jobProcessor = new JobProcessor(
            $container->get(JobRepositoryInterface::class),
            $container->get(JobLogRepositoryInterface::class),
            $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
            $container->has(SentryLifecycle::class) ? $container->get(SentryLifecycle::class) : null,
            $container->has(SentryReporter::class) ? $container->get(SentryReporter::class) : null,
        );

        $config   = $container->has('config') ? $container->get('config') : [];
        $handlers = $config['worker']['job_handlers'] ?? [];

        foreach ($handlers as $type => $serviceId) {
            $handler = $container->get($serviceId);

            if (! $handler instanceof JobHandlerInterface) {
                throw new RuntimeException(sprintf(
                    'Worker job handler "%s" for type "%s" must implement %s.',
                    $serviceId,
                    $type,
                    JobHandlerInterface::class,
                ));
            }

            $jobProcessor->registerHandler($type, $handler);
        }

        return $jobProcessor;
    }
}
