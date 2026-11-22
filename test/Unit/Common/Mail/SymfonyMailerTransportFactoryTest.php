<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Mail;

use Common\Mail\SymfonyMailerTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class SymfonyMailerTransportFactoryTest extends TestCase
{
    public function testItBuildsSmtpTransportFromDsn(): void
    {
        $transport = $this->transportFor('smtp://mailpit:1025');

        self::assertInstanceOf(EsmtpTransport::class, $transport);
        self::assertSame('smtp://mailpit:1025', (string) $transport);
    }

    public function testItBuildsNullTransportFromDsn(): void
    {
        self::assertInstanceOf(NullTransport::class, $this->transportFor('null://null'));
    }

    private function transportFor(string $dsn): TransportInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn([
            'mail' => [
                'dsn' => $dsn,
            ],
        ]);

        $mailer = new SymfonyMailerTransportFactory()($container, Mailer::class);

        return new ReflectionProperty(Mailer::class, 'transport')->getValue($mailer);
    }
}
