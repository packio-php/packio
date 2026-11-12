<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Application\Service;

use Packio\Publication\Application\Service\ComposerVersionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerVersionResolver::class)]
final class ComposerVersionResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $composer
     */
    #[DataProvider('versionCases')]
    public function testResolve(array $composer, string $reference, string $defaultBranch, string $expected): void
    {
        self::assertSame($expected, new ComposerVersionResolver()->resolve($composer, $reference, $defaultBranch));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string, string}>
     */
    public static function versionCases(): iterable
    {
        yield 'composer version wins' => [[
            'version'        => '2.0.0',
        ], 'v1.0.0', 'main', '2.0.0'];

        yield 'semantic tag' => [[], 'v1.2.3', 'main', '1.2.3'];

        yield 'short semantic tag' => [[], 'v2.1', 'main', '2.1'];

        yield 'short numeric tag' => [[], '2.0', 'main', '2.0'];

        yield 'major numeric tag' => [[], '2', 'main', '2'];

        yield 'default branch' => [[], 'main', 'main', 'dev-main'];

        yield 'branch alias' => [
            [
                'extra'          => [
                    'branch-alias' => [
                        'develop'      => '2.0.x-dev',
                    ],
                ],
            ],
            'develop',
            'main',
            '2.0.x-dev',
        ];

        yield 'composer style branch alias' => [
            [
                'extra' => [
                    'branch-alias' => [
                        'dev-2.x' => '2.0.x-dev',
                    ],
                ],
            ],
            '2.x',
            'main',
            '2.0.x-dev',
        ];

        yield 'numeric x branch' => [[], '2.x', 'main', '2.x-dev'];

        yield 'numeric minor x branch' => [[], '2.0.x', 'main', '2.0.x-dev'];

        yield 'fallback dev reference' => [[], 'feature/test', 'main', 'dev-feature/test'];
    }
}
