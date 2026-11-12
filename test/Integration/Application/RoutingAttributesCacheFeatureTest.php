<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function file_get_contents;
use function is_file;

final class RoutingAttributesCacheFeatureTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableRoutingAttributesCache();
        $this->rebuildContainer();
    }

    #[Test]
    public function aProtectedApiRouteBootsFromANewlyCompiledRouteCache(): void
    {
        $this->get('/api/v1/me', [
            'Accept' => 'application/json',
        ])->assertStatus(401);

        $cacheFile = $this->routingAttributesCacheFile();
        self::assertFileExists($cacheFile);
        self::assertStringContainsString('ApiAuthenticationContextMiddleware', (string) file_get_contents($cacheFile));
        self::assertStringNotContainsString('ApiTokenAuthenticationMiddleware', (string) file_get_contents($cacheFile));

        $this->rebuildApplication();

        $this->get('/api/v1/me', [
            'Accept' => 'application/json',
        ])->assertStatus(401);
        self::assertTrue(is_file($cacheFile));
    }
}
