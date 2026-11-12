<?php

declare(strict_types=1);

namespace Common\Mail;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\NullTransport;

final class SymfonyMailerTransportFactory implements FactoryInterface
{
    /** @param null|array<string, mixed> $options */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): SymfonyMailerInterface
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('config');
        $dsn    = (string) ($config['mail']['dsn'] ?? 'smtp://localhost:25');

        return new Mailer(
            'null://null' === $dsn
                ? new NullTransport()
                : Transport::fromDsn($dsn),
        );
    }
}
