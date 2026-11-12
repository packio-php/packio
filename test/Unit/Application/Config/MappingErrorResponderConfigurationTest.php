<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Packio\Application\Validation\MappingErrorResponder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\Mezzio\Valinor\Error\DefaultMappingErrorResponder;
use Sirix\Mezzio\Valinor\Error\MappingErrorResponderInterface;
use Sirix\Mezzio\Valinor\Factory\MappingErrorResponderResolverFactory;
use stdClass;

use function dirname;

final class MappingErrorResponderConfigurationTest extends TestCase
{
    #[Test]
    public function itRegistersThePlatformResponderAsTheGlobalDefault(): void
    {
        /** @var array{dependencies: array{aliases: array<class-string, class-string>}, ConfigAbstractFactory::class: array<class-string, list<class-string>>} $config */
        $config = require dirname(__DIR__, 4) . '/src/Application/config/dependencies.config.php';

        self::assertSame(
            MappingErrorResponder::class,
            $config['dependencies']['aliases'][MappingErrorResponderInterface::class],
        );
        self::assertContains(
            DefaultMappingErrorResponder::class,
            $config[ConfigAbstractFactory::class][MappingErrorResponder::class],
        );
    }

    #[Test]
    public function itResolvesTheRegisteredDefaultResponder(): void
    {
        $defaultMappingErrorResponder = new DefaultMappingErrorResponder(new ResponseFactory(), new StreamFactory());
        $resolver                     = (new MappingErrorResponderResolverFactory())(new ServiceManager([
            'services' => [
                MappingErrorResponderInterface::class  => $defaultMappingErrorResponder,
                DefaultMappingErrorResponder::class    => $defaultMappingErrorResponder,
            ],
        ]));

        self::assertSame($defaultMappingErrorResponder, $resolver->resolve(null));
    }

    #[Test]
    public function itRejectsAnInvalidGlobalResponderServiceType(): void
    {
        $serviceManager = new ServiceManager([
            'services' => [
                MappingErrorResponderInterface::class  => new stdClass(),
                DefaultMappingErrorResponder::class    => new DefaultMappingErrorResponder(new ResponseFactory(), new StreamFactory()),
            ],
        ]);

        $this->expectException(InvalidContainerServiceException::class);
        (new MappingErrorResponderResolverFactory())($serviceManager);
    }
}
