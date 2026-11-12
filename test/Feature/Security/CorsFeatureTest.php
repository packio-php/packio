<?php

declare(strict_types=1);

namespace PackioTest\Feature\Security;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CorsFeatureTest extends FeatureTestCase
{
    #[Test]
    public function itAnswersPreflightRequestsForConfiguredOrigin(): void
    {
        $testResponse = $this->request('OPTIONS', '/api/v1/health', [], [
            'Access-Control-Request-Headers' => 'Authorization,X-API-TOKEN,Content-Type',
            'Access-Control-Request-Method'  => 'GET',
            'Origin'                         => 'https://packio.us',
        ]);

        $testResponse
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://packio.us')
            ->assertHeader('Access-Control-Allow-Headers')
            ->assertHeader('Access-Control-Allow-Methods')
        ;

        self::assertStringContainsString('GET', $testResponse->header('Access-Control-Allow-Methods'));
        self::assertStringContainsString('Authorization', $testResponse->header('Access-Control-Allow-Headers'));
        self::assertSame('600', $testResponse->header('Access-Control-Max-Age'));
    }

    #[Test]
    public function sameOriginPreflightFallsThroughToTheOriginalMethodHandling(): void
    {
        $this->withHost('packio.us');

        $this->request('OPTIONS', '/api/v1/health', [], [
            'Access-Control-Request-Method' => 'GET',
            'Origin'                        => 'https://packio.us',
        ])
            ->assertStatus(200)
            ->assertHeader('Allow', 'GET,HEAD')
        ;
    }

    #[Test]
    public function itAddsCorsHeadersToConfiguredOriginRequests(): void
    {
        $this->get('/api/v1/health', [
            'Origin' => 'https://packio.us',
        ])
            ->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'https://packio.us')
            ->assertHeader('Access-Control-Expose-Headers')
        ;
    }

    #[Test]
    public function nativeCorsHandlesMatchedUnmatchedAndForbiddenOrigins(): void
    {
        $this->get('/api/v1/health', [
            'Origin' => 'https://packio.us',
        ])
            ->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'https://packio.us')
        ;

        $testResponse = $this->get('/missing-cors-route', [
            'Origin' => 'https://packio.us',
        ]);
        $testResponse->assertStatus(404);
        self::assertSame('', $testResponse->header('Access-Control-Allow-Origin'));

        $forbidden = $this->get('/api/v1/health', [
            'Origin' => 'https://untrusted.example',
        ]);
        $forbidden->assertStatus(403);
        self::assertSame('', $forbidden->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function runtimeSettingCanDisableCorsHeaders(): void
    {
        $projectSettings = $this->getService(ProjectSettingsApiFacadeInterface::class);
        self::assertInstanceOf(ProjectSettingsApiFacadeInterface::class, $projectSettings);

        $projectSettings->updateOverrides([
            ProjectSettingsApiFacadeInterface::HTTP_CORS_ENABLED => RuntimeSettingOverride::Off->value,
        ]);

        $testResponse = $this->get('/api/v1/health', [
            'Origin' => 'https://packio.us',
        ]);

        $testResponse->assertStatus(200);
        self::assertSame('', $testResponse->header('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function registryHostPreflightCompletesCorsBeforeTheHostRestriction(): void
    {
        $this->withHost('tenant.packio.us');

        foreach (['/admin', '/api/v1/health', '/health', '/ready'] as $path) {
            $this->request('OPTIONS', $path, [], [
                'Access-Control-Request-Method' => 'GET',
                'Origin'                        => 'https://packio.us',
            ])
                ->assertStatus(204)
                ->assertHeader('Access-Control-Allow-Origin', 'https://packio.us')
            ;
        }
    }

    #[Test]
    public function registryHostPreflightReachesTheHostRestrictionWhenCorsIsDisabled(): void
    {
        $projectSettings = $this->getService(ProjectSettingsApiFacadeInterface::class);
        self::assertInstanceOf(ProjectSettingsApiFacadeInterface::class, $projectSettings);
        $projectSettings->updateOverrides([
            ProjectSettingsApiFacadeInterface::HTTP_CORS_ENABLED => RuntimeSettingOverride::Off->value,
        ]);
        $this->withHost('tenant.packio.us');

        $testResponse = $this->request('OPTIONS', '/api/v1/health', [], [
            'Access-Control-Request-Method' => 'GET',
            'Origin'                        => 'https://packio.us',
        ]);

        $testResponse
            ->assertStatus(302)
            ->assertHeader('Location', 'https://packio.us/api/v1/health')
        ;
        self::assertSame('', $testResponse->header('Access-Control-Allow-Origin'));
    }
}
