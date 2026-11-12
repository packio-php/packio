<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\OpenApi;

use Packio\Rest\Infrastructure\OpenApi\OpenApiSpecGenerator;
use PHPUnit\Framework\TestCase;

use function array_column;
use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class OpenApiSpecGeneratorTest extends TestCase
{
    public function testItScansSourcesOnlyOnce(): void
    {
        $dir = sys_get_temp_dir() . '/' . uniqid('openapi-spec-', true);
        mkdir($dir);
        $class = 'Annotated' . bin2hex(random_bytes(4));
        $file  = $dir . '/' . $class . '.php';
        file_put_contents($file, <<<PHP
            <?php

            namespace PackioTest\\Scratch\\OpenApiOnce;

            use OpenApi\\Attributes as OA;

            #[OA\\Info(title: 'Scratch', version: '1.0.0')]
            final class {$class}
            {
                #[OA\\Get(path: '/x', responses: [new OA\\Response(response: 200, description: 'ok')])]
                public function x(): void {}
            }
            PHP);

        try {
            require_once $file; // swagger-php only reads classes it can autoload
            $generator = new OpenApiSpecGenerator([
                'scan_paths' => [$dir],
            ]);
            $first = $generator->generate(true);
            unlink($file);

            self::assertArrayHasKey('/x', $first['paths']);
            self::assertSame($first, $generator->generate(true));
        } finally {
            @unlink($file);
            rmdir($dir);
        }
    }

    public function testItFiltersTheAdminSectionOnEveryCall(): void
    {
        $openApiSpecGenerator = new OpenApiSpecGenerator();

        self::assertArrayHasKey('/api/v1/admin/users', $openApiSpecGenerator->generate(true)['paths']);

        $public = $openApiSpecGenerator->generate(false);
        self::assertArrayNotHasKey('/api/v1/admin/users', $public['paths']);
        self::assertNotContains('Admin', array_column($public['tags'], 'name'));
        foreach (['AdminUserUpdateRequest', 'AdminSettingsUpdateRequest', 'AdminOrganizationUpdateRequest'] as $schema) {
            self::assertArrayNotHasKey($schema, $public['components']['schemas']);
        }

        self::assertArrayHasKey('/api/v1/admin/users', $openApiSpecGenerator->generate(true)['paths']);
    }

    public function testItMatchesTheCommittedArtifact(): void
    {
        $artifact = json_decode((string) file_get_contents(dirname(__DIR__, 5) . '/public/openapi.json'), true);

        self::assertSame($artifact, new OpenApiSpecGenerator()->generate(true));
    }
}
