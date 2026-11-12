<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\ObjectMapper;

use Common\Infrastructure\ObjectMapper\ObjectMapperFactory;
use Common\Infrastructure\ObjectMapper\Transformer\UuidInterfaceToStringTransformer;
use Laminas\ServiceManager\ServiceManager;
use Packio\Source\Api\Dto\ProviderResourceDto;
use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\ObjectMapper\Runtime\ObjectMapper;

use function array_merge;
use function bin2hex;
use function dirname;
use function glob;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

final class SourceObjectMapperConfigurationTest extends TestCase
{
    private ?string $cacheDirectory = null;

    protected function tearDown(): void
    {
        if (null === $this->cacheDirectory
            || ! str_starts_with($this->cacheDirectory, sys_get_temp_dir() . '/packio-source-object-mapper-')
            || ! is_dir($this->cacheDirectory)) {
            return;
        }

        $formatCacheDirectory = $this->cacheDirectory . '/v8';
        foreach ([
            ...(glob($formatCacheDirectory . '/*') ?: []),
            ...(glob($formatCacheDirectory . '/.*') ?: []),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($formatCacheDirectory)) {
            rmdir($formatCacheDirectory);
        }

        rmdir($this->cacheDirectory);
    }

    #[Test]
    public function itMapsProviderResourceDataWithPublicFieldRenames(): void
    {
        $providerResourceDto = $this->objectMapper()->map(
            new ProviderResourceData(
                id: 'resource-1',
                name: 'packio',
                type: 'github',
                url: 'https://github.com/packio-php/packio',
                defaultBranch: 'trunk',
                description: 'Ignored public description.',
                private: true,
            ),
            ProviderResourceDto::class,
        );

        self::assertSame('resource-1', $providerResourceDto->id);
        self::assertSame('github', $providerResourceDto->provider);
        self::assertSame('packio', $providerResourceDto->name);
        self::assertSame('https://github.com/packio-php/packio', $providerResourceDto->canonicalUrl);
        self::assertSame('trunk', $providerResourceDto->defaultRef);
        self::assertTrue($providerResourceDto->private);
    }

    #[Test]
    public function itMapsSourceOAuthAccountIdentifierThroughTheUuidTransformer(): void
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'gitlab',
            providerEmail: 'user@example.com',
            providerUsername: 'packio-user',
        );
        $sourceOAuthAccount->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        $sourceOAuthAccountDto = $this->objectMapper()->map($sourceOAuthAccount, SourceOAuthAccountDto::class);

        self::assertSame('00000000-0000-0000-0000-000000000001', $sourceOAuthAccountDto->id);
        self::assertSame('gitlab', $sourceOAuthAccountDto->provider);
        self::assertSame('packio-user', $sourceOAuthAccountDto->providerUsername);
        self::assertSame('user@example.com', $sourceOAuthAccountDto->providerEmail);
    }

    private function objectMapper(): ObjectMapper
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/packio-source-object-mapper-' . bin2hex(random_bytes(8));
        $sourceConfig         = require dirname(__DIR__, 5) . '/src/Module/Source/config/object-mapper.config.php';

        $objectMapper = (new ObjectMapperFactory())(new ServiceManager([
            'services' => [
                'config'                                       => [
                    'object_mapper' => array_merge(
                        $sourceConfig['object_mapper'],
                        [
                            'cache_dir'          => $this->cacheDirectory,
                            'generate_on_demand' => false,
                            'transformers'       => [UuidInterfaceToStringTransformer::class],
                        ],
                    ),
                ],
                UuidInterfaceToStringTransformer::class        => new UuidInterfaceToStringTransformer(),
            ],
        ]));
        $objectMapper->warmup();

        return $objectMapper;
    }
}
