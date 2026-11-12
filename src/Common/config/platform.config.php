<?php

declare(strict_types=1);

use Common\Branding\BrandingConfig;
use Common\Branding\BrandingOptions;
use Common\Mail\MailerInterface;
use Common\Mail\SymfonyMailer;
use Common\Mail\SymfonyMailerTransportFactory;
use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Common\Security\StoredSecretResolver;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Sirix\Config\Factory\ValinorConfigFactory;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;

return [
    'dependencies'               => [
        'aliases'   => [
            SecretEncryptorInterface::class => SodiumSecretEncryptor::class,
            MailerInterface::class          => SymfonyMailer::class,
        ],
        'factories' => [
            BrandingOptions::class          => [ValinorConfigFactory::class, 'config.branding'],
            BrandingConfig::class           => ConfigAbstractFactory::class,
            SodiumSecretEncryptor::class    => ConfigAbstractFactory::class,
            StoredSecretResolver::class     => ConfigAbstractFactory::class,
            SymfonyMailer::class            => ConfigAbstractFactory::class,
            SymfonyMailerInterface::class   => SymfonyMailerTransportFactory::class,
        ],
    ],
    ConfigAbstractFactory::class => [
        BrandingConfig::class        => [
            BrandingOptions::class,
        ],
        SodiumSecretEncryptor::class => [
            'config.security.secret_encryption_key',
            'config.packio.production_mode',
        ],
        StoredSecretResolver::class  => [
            SecretEncryptorInterface::class,
        ],
        SymfonyMailer::class         => [
            SymfonyMailerInterface::class,
            'config.mail',
            // LoggerInterface::class,  // optional
        ],
    ],
];
