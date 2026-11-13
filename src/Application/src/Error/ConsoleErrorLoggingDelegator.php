<?php

declare(strict_types=1);

namespace Packio\Application\Error;

use Common\Logging\SafeExceptionContext;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use UnexpectedValueException;

/** Logs failed console commands redacted; the vendor Sentry listener would log the raw message and trace. */
final class ConsoleErrorLoggingDelegator
{
    public function __invoke(ContainerInterface $container, string $serviceName, callable $callback): EventDispatcher
    {
        unset($serviceName);

        $eventDispatcher = $callback();
        if (! $eventDispatcher instanceof EventDispatcher) {
            throw new UnexpectedValueException('Console error logging delegator expects an EventDispatcher service.');
        }

        $logger = $container->get(LoggerInterface::class);
        if (! $logger instanceof LoggerInterface) {
            throw new UnexpectedValueException('Console error logging delegator expects a LoggerInterface service.');
        }

        $eventDispatcher->addListener(
            ConsoleEvents::ERROR,
            static function(ConsoleErrorEvent $consoleErrorEvent) use ($logger): void {
                $logger->error('Command failed with exception', [
                    'command'   => $consoleErrorEvent->getCommand()?->getName(),
                    'exception' => SafeExceptionContext::exception($consoleErrorEvent->getError()),
                ]);
            },
        );

        return $eventDispatcher;
    }
}
