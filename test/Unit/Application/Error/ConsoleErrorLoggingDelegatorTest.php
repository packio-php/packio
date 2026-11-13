<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Error;

use const JSON_THROW_ON_ERROR;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Packio\Application\Error\ConsoleErrorLoggingDelegator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function json_encode;

final class ConsoleErrorLoggingDelegatorTest extends TestCase
{
    public function testFailedCommandIsLoggedOnceWithRedactedContext(): void
    {
        $testHandler = new TestHandler();
        $container   = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([[LoggerInterface::class, new Logger('test', [$testHandler])]]);

        $eventDispatcher = new ConsoleErrorLoggingDelegator()($container, EventDispatcher::class, static fn (): EventDispatcher => new EventDispatcher());
        $eventDispatcher->dispatch(
            new ConsoleErrorEvent(
                new ArrayInput([]),
                new NullOutput(),
                new RuntimeException('Authorization: Bearer private-secret'),
                new Command('demo:fail'),
            ),
            ConsoleEvents::ERROR,
        );

        $records = $testHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Error, $records[0]->level);
        self::assertSame('demo:fail', $records[0]->context['command']);
        self::assertSame(RuntimeException::class, $records[0]->context['exception']['class']);
        self::assertStringNotContainsString('private-secret', json_encode($records[0]->context, JSON_THROW_ON_ERROR));
    }
}
