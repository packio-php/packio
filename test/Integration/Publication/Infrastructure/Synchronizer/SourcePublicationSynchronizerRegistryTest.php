<?php

declare(strict_types=1);

namespace PackioTest\Integration\Publication\Infrastructure\Synchronizer;

use InvalidArgumentException;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerRegistry;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SourcePublicationSynchronizerRegistry::class)]
final class SourcePublicationSynchronizerRegistryTest extends IntegrationTestCase
{
    public function testRegistryResolvesAllSevenProviders(): void
    {
        $registry = $this->container->get(SourcePublicationSynchronizerRegistry::class);

        foreach (['github', 'gitlab', 'gitlab_self_hosted', 'generic_git', 'bitbucket', 'codeberg', 'forgejo_self_hosted'] as $provider) {
            self::assertSame($provider, $registry->forProvider($provider)->provider());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Publication synchronizer for provider "svn" is not registered.');

        $registry->forProvider('svn');
    }
}
