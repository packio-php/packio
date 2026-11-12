<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Release;

use DateTimeImmutable;
use DateTimeZone;
use Packio\Application\Release\AppReleaseMetadataGenerator;
use PackioTest\Support\Base\UnitTestCase;
use RuntimeException;

final class AppReleaseMetadataGeneratorTest extends UnitTestCase
{
    public function testGeneratesStableVersionFromExactSemverTag(): void
    {
        $appReleaseMetadataGenerator = new AppReleaseMetadataGenerator(
            basePath: '/app',
            run: $this->runner([
                'rev-parse --short HEAD'                              => 'abc1234',
                'rev-parse --abbrev-ref HEAD'                         => 'main',
                'status --porcelain'                                  => '',
                "tag --points-at HEAD --list 'v[0-9]*.[0-9]*.[0-9]*'" => "v1.2.2\nv1.2.3",
            ]),
            builtAt: new DateTimeImmutable('2026-05-23 12:00:00', new DateTimeZone('UTC')),
        );

        $metadata = $appReleaseMetadataGenerator->generate();

        self::assertSame('1.2.3', $metadata['version']);
        self::assertSame('stable', $metadata['channel']);
        self::assertSame(0, $metadata['distance']);
        self::assertSame('v1.2.3', $metadata['tag']);
        self::assertFalse($metadata['dirty']);
        self::assertSame('2026-05-23T12:00:00Z', $metadata['built_at']);
    }

    public function testGeneratesDevVersionFromConventionalCommitsSinceLatestTag(): void
    {
        $appReleaseMetadataGenerator = new AppReleaseMetadataGenerator(
            basePath: '/app',
            run: $this->runner([
                'rev-parse --short HEAD'                                          => 'def5678',
                'rev-parse --abbrev-ref HEAD'                                     => 'main',
                'status --porcelain'                                              => '',
                "tag --points-at HEAD --list 'v[0-9]*.[0-9]*.[0-9]*'"             => '',
                "describe --tags --abbrev=0 --match 'v[0-9]*.[0-9]*.[0-9]*' HEAD" => 'v1.2.3',
                "rev-list --count 'v1.2.3'..HEAD"                                 => '2',
                "log --format=%s 'v1.2.3'..HEAD"                                  => "fix: repair sync\nfeat: add invitations",
                "log --format=%b 'v1.2.3'..HEAD"                                  => '',
            ]),
            builtAt: new DateTimeImmutable('2026-05-23 12:00:00', new DateTimeZone('UTC')),
        );

        $metadata = $appReleaseMetadataGenerator->generate();

        self::assertSame('1.3.0-dev.2+def5678', $metadata['version']);
        self::assertSame('dev', $metadata['channel']);
        self::assertSame(2, $metadata['distance']);
        self::assertSame('v1.2.3', $metadata['tag']);
    }

    public function testRejectsUnavailableGitInsteadOfGeneratingAnEmptyBuildIdentifier(): void
    {
        $appReleaseMetadataGenerator = new AppReleaseMetadataGenerator(basePath: '/app', run: $this->runner([]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read Git HEAD');

        $appReleaseMetadataGenerator->generate();
    }

    public function testCanGenerateVersionFromAnUntaggedRepository(): void
    {
        $appReleaseMetadataGenerator = new AppReleaseMetadataGenerator(basePath: '/app', run: $this->runner([
            'rev-parse --short HEAD' => 'abc1234',
            'rev-list --count HEAD'  => '1',
        ]));

        self::assertSame('0.0.1-dev.1+abc1234', $appReleaseMetadataGenerator->generate()['version']);
    }

    /** @param array<string, string> $responses */
    private function runner(array $responses): callable
    {
        return static fn (string $command): string => $responses[$command] ?? '';
    }
}
