<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Composer;

use DateTimeImmutable;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateProvider;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ComposerReleaseDateProviderTest extends TestCase
{
    public function testFallsBackWhenResolverFails(): void
    {
        $resolver = $this->createMock(ComposerReleaseDateResolverInterface::class);
        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new RuntimeException('Composer failed.'))
        ;

        $composerReleaseDateMap = new ComposerReleaseDateProvider($resolver)->resolve(
            [
                'provider' => 'github',
                'owner'    => 'vendor',
            ],
            'token',
            '2024-05-01T12:00:00+00:00',
        );

        self::assertSame('2024-05-01T12:00:00+00:00', $composerReleaseDateMap->find('1.0.0', 'ref'));
    }

    public function testFallsBackForGenericGitWithNonRootComposerJsonPath(): void
    {
        $resolver = $this->createMock(ComposerReleaseDateResolverInterface::class);
        $resolver
            ->expects(self::never())
            ->method('resolve')
        ;

        $composerReleaseDateMap = new ComposerReleaseDateProvider($resolver)->resolve(
            [
                'provider'       => 'generic_git',
                'providerConfig' => [
                    'composer_json_path' => 'packages/composer.json',
                ],
            ],
            'token',
            '2024-05-02T12:00:00+00:00',
        );

        self::assertSame('2024-05-02T12:00:00+00:00', $composerReleaseDateMap->find('1.0.0', 'ref'));
    }

    public function testDelegatesRootComposerJsonSourcesToResolver(): void
    {
        $composerReleaseDateMap = new ComposerReleaseDateMap(
            [
                'main' => '2024-05-03T12:00:00+00:00',
            ],
            new DateTimeImmutable('2024-05-04T12:00:00+00:00'),
        );

        $resolver = $this->createMock(ComposerReleaseDateResolverInterface::class);
        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($composerReleaseDateMap)
        ;

        $map = new ComposerReleaseDateProvider($resolver)->resolve(
            [
                'provider' => 'github',
                'owner'    => 'vendor',
            ],
            'token',
            '2024-05-02T12:00:00+00:00',
        );

        self::assertSame($composerReleaseDateMap, $map);
    }

    /**
     * @param array<string, mixed> $source
     */
    #[DataProvider('fallbackSources')]
    public function testFallsBackForProvidersWithoutFixedHost(array $source): void
    {
        $resolver = $this->createMock(ComposerReleaseDateResolverInterface::class);
        $resolver
            ->expects(self::never())
            ->method('resolve')
        ;

        $composerReleaseDateMap = new ComposerReleaseDateProvider($resolver)->resolve($source, 'token', '2026-01-01T00:00:00+00:00');

        self::assertSame('2026-01-01T00:00:00+00:00', $composerReleaseDateMap->find('1.0.0', 'v1.0.0'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function fallbackSources(): iterable
    {
        yield 'gitlab self hosted' => [[
            'provider'       => 'gitlab_self_hosted',
            'providerConfig' => [
                'base_url' => 'https://git.example.test',
            ],
        ]];

        yield 'generic git root composer.json' => [[
            'provider'       => 'generic_git',
            'providerConfig' => [
                'composer_json_path' => 'composer.json',
            ],
        ]];

        yield 'unknown provider' => [[
            'provider' => 'svn',
        ]];
    }
}
