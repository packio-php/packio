<?php

declare(strict_types=1);

namespace PackioTest\Unit\Publication\Infrastructure\Composer;

use DateTimeImmutable;
use Packio\Publication\Infrastructure\Composer\ComposerReleaseDateMap;
use PHPUnit\Framework\TestCase;

final class ComposerReleaseDateMapTest extends TestCase
{
    public function testFindsReleaseDateByVersionReferencePair(): void
    {
        $composerReleaseDateMap = new ComposerReleaseDateMap(
            [
                '1.0.0.0:abc123' => '2024-04-01T12:00:00+00:00',
                'main'           => '2024-04-02T12:00:00+00:00',
            ],
            new DateTimeImmutable('2024-04-03T12:00:00+00:00'),
        );

        self::assertSame('2024-04-01T12:00:00+00:00', $composerReleaseDateMap->find('1.0.0.0', 'abc123'));
    }

    public function testFindsReleaseDateForVersionWithoutLeadingV(): void
    {
        $composerReleaseDateMap = new ComposerReleaseDateMap(
            [
                '1.2.3' => '2024-04-04T12:00:00+00:00',
            ],
            new DateTimeImmutable('2024-04-05T12:00:00+00:00'),
        );

        self::assertSame('2024-04-04T12:00:00+00:00', $composerReleaseDateMap->find('v1.2.3', 'def456'));
    }

    public function testFallsBackToCurrentResolverDateWhenComposerHasNoMatchingVersion(): void
    {
        $composerReleaseDateMap = new ComposerReleaseDateMap([], new DateTimeImmutable('2024-04-06T15:30:00+03:00'));

        self::assertSame('2024-04-06T12:30:00+00:00', $composerReleaseDateMap->find('2.0.0', 'missing-ref'));
    }
}
