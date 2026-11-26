<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Webhook;

use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SourceWebhookRegistrarRegistryTest extends IntegrationTestCase
{
    #[Test]
    public function registryResolvesAllSixProviders(): void
    {
        $registry = $this->container->get(WebhookRegistrarRegistryInterface::class);

        foreach (['github', 'gitlab', 'gitlab_self_hosted', 'bitbucket', 'codeberg', 'forgejo_self_hosted'] as $provider) {
            self::assertTrue($registry->hasProvider($provider), $provider);
            self::assertSame($provider, $registry->forProvider($provider)?->provider());
        }
    }
}
