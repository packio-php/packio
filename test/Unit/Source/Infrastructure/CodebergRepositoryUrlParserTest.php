<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure;

use InvalidArgumentException;
use Packio\Source\Infrastructure\CodebergRepositoryUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodebergRepositoryUrlParserTest extends TestCase
{
    #[DataProvider('validUrls')]
    #[Test]
    public function parseAcceptsCodebergRepositoryUrls(string $url): void
    {
        $parsed = new CodebergRepositoryUrlParser()->parse($url);

        self::assertSame('owner', $parsed['owner']);
        self::assertSame('repo', $parsed['repository']);
        self::assertSame('https://codeberg.org/owner/repo', $parsed['normalized_url']);
    }

    /** @return iterable<string, array{string}> */
    public static function validUrls(): iterable
    {
        return [
            'https'            => ['https://codeberg.org/owner/repo'],
            'https git suffix' => ['https://codeberg.org/owner/repo.git'],
            'ssh'              => ['git@codeberg.org:owner/repo'],
            'ssh git suffix'   => ['git@codeberg.org:owner/repo.git'],
        ];
    }

    #[DataProvider('instanceUrls')]
    #[Test]
    public function parseAcceptsInstanceUrlsWithPath(string $url): void
    {
        $parsed = new CodebergRepositoryUrlParser()->parse($url, 'https://example.com/git');

        self::assertSame('owner', $parsed['owner']);
        self::assertSame('repo', $parsed['repository']);
        self::assertSame('https://example.com/git/owner/repo', $parsed['normalized_url']);
        self::assertSame('https://example.com/git', $parsed['base_url']);
    }

    /** @return iterable<string, array{string}> */
    public static function instanceUrls(): iterable
    {
        return [
            'https'            => ['https://example.com/git/owner/repo'],
            'https git suffix' => ['https://example.com/git/owner/repo.git'],
        ];
    }

    #[Test]
    public function parseAcceptsSshFormOnlyForBaseUrlWithoutPath(): void
    {
        $codebergRepositoryUrlParser = new CodebergRepositoryUrlParser();

        self::assertSame('https://example.com/owner/repo', $codebergRepositoryUrlParser->parse('git@example.com:owner/repo.git', 'https://example.com/')['normalized_url']);

        $this->expectException(InvalidArgumentException::class);
        $codebergRepositoryUrlParser->parse('git@example.com:owner/repo', 'https://example.com/git');
    }

    #[Test]
    public function parseRejectsForeignHostForInstance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CodebergRepositoryUrlParser()->parse('https://codeberg.org/owner/repo', 'https://example.com/git');
    }

    #[Test]
    public function parseRejectsOtherHosts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only codeberg.org repository URLs are supported for this flow.');

        new CodebergRepositoryUrlParser()->parse('https://example.org/owner/repo');
    }
}
