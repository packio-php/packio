<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Sirix\Mezzio\Routing\Attributes\RouteMiddlewareDisplay;

use function bin2hex;
use function file_get_contents;
use function ksort;
use function random_bytes;
use function unlink;

/**
 * Cold extraction and the compiled route cache must produce equivalent route
 * definitions for every mapped route: path, methods, the full middleware
 * pipeline identity/order (via the display contract used by cold and compiled
 * registration alike) and the mapping/validation/policy defaults. Rehydrated
 * requests must run the same pipeline — a validated route stops before the
 * handler on validation failure.
 */
final class MappedRequestRouteCacheParityTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableRoutingAttributesCache();
        $this->rebuildContainer();
    }

    #[Test]
    public function compiledRouteDefinitionsMatchColdExtractionForMappedRoutes(): void
    {
        @unlink($this->routingAttributesCacheFile());
        $this->rebuildApplication();

        $coldDefinitions = $this->mappedRouteDefinitions();
        self::assertGreaterThan(0, $coldDefinitions);

        $this->rebuildContainer();
        $this->rebuildApplication();
        $this->get('/', [
            'Accept' => 'text/html',
        ]);

        self::assertFileExists($this->routingAttributesCacheFile());
        self::assertStringContainsString(
            'ValidateMappedRequestMiddleware',
            (string) file_get_contents($this->routingAttributesCacheFile()),
        );

        $this->rebuildApplication();

        $compiledDefinitions = $this->mappedRouteDefinitions();

        self::assertSame(
            $coldDefinitions,
            $compiledDefinitions,
            'Cold-extracted and compiled route definitions must be identical for every mapped route.',
        );
    }

    #[Test]
    public function aValidatedRouteFromTheCompiledCacheStopsBeforeTheHandlerOnValidationFailure(): void
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'cache-parity-' . bin2hex(random_bytes(6)) . '@example.test',
            'Cache Parity User',
            'Password1!',
        );
        $identity->markUserEmailAsVerified($userDto->id);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');

        $this->rebuildApplication();

        $testResponse = $this->withCsrf()->post('/profile/password', [
            'current_password'          => 'Password1!',
            'new_password'              => 'NewPassword2!',
            'new_password_confirmation' => 'Totally-Different3!',
        ]);

        self::assertSame(302, $testResponse->status());

        $this->post('/auth/logout')->assertRedirect();

        $reflectionProperty = new ReflectionProperty(FeatureTestCase::class, 'csrfToken');
        $reflectionProperty->setValue($this, 'test-csrf-token');

        $newPasswordLogin = $this->withCsrf('/auth/login')->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => 'NewPassword2!',
        ]);

        self::assertSame(302, $newPasswordLogin->status());
        self::assertSame('/auth/login', $newPasswordLogin->header('Location'));
    }

    /**
     * @return array<string, array{path: string, display: string, methods: null|list<string>}>
     */
    private function mappedRouteDefinitions(): array
    {
        $definitions = [];

        foreach ($this->application()->getRoutes() as $route) {
            $options = $route->getOptions();

            if (! isset($options['valinor_mappings'])) {
                continue;
            }

            $definitions[$route->getName()] = [
                'path'                       => $route->getPath(),
                'display'                    => (string) ($options[RouteMiddlewareDisplay::ROUTE_OPTION_MIDDLEWARE_DISPLAY] ?? ''),
                'methods'                    => $route->getAllowedMethods(),
                'valinor_mappings'           => $options['valinor_mappings'],
                'mapped_request_validations' => $options['mapped_request_validations'] ?? null,
                'validation_failed'          => $options['validation_failed'] ?? null,
            ];
        }

        ksort($definitions);

        return $definitions;
    }
}
