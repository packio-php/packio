<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Provider;

use Common\Provider\SourceProviderMetadata;
use Common\Provider\SourceProviderMetadataCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(SourceProviderMetadataCatalog::class)]
final class SourceProviderMetadataCatalogTest extends TestCase
{
    #[Test]
    #[DataProvider('selfHostedKeys')]
    public function isSelfHostedReflectsMetadata(string $key, bool $expected): void
    {
        self::assertSame($expected, new SourceProviderMetadataCatalog()->isSelfHosted($key));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function selfHostedKeys(): iterable
    {
        yield 'gitlab self-hosted' => ['gitlab_self_hosted', true];

        yield 'forgejo self-hosted' => ['forgejo_self_hosted', true];

        yield 'codeberg' => ['codeberg', false];

        yield 'unknown' => ['nope', false];
    }

    #[Test]
    public function forgejoSelfHostedIsNotALoginOAuthProvider(): void
    {
        $keys = array_map(
            static fn (SourceProviderMetadata $metadata): string => $metadata->key,
            new SourceProviderMetadataCatalog()->loginOAuthBackedSourceProviders(),
        );

        self::assertNotContains('forgejo_self_hosted', $keys);
        self::assertContains('codeberg', $keys);
    }
}
