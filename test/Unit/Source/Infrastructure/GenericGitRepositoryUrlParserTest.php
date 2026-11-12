<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure;

use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use InvalidArgumentException;
use Packio\Source\Infrastructure\GenericGitRepositoryUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GenericGitRepositoryUrlParserTest extends TestCase
{
    #[DataProvider('invalidUrls')]
    public function testRejectsAmbiguousUrls(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GenericGitRepositoryUrlParser()->parse($url);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        foreach (['https://user:password@example.com/repo', 'https://example.com/repo?q=x', 'https://example.com/repo#fragment', 'https://example.com/a/../repo', 'https://example.com/a/%2e%2e/repo', 'https://example.com/a/%2frepo', 'https://example.com/a//repo', 'https://GITHUB.COM/org/repo', 'https://gitlab.com:443/org/repo', 'https://example.com:8443/repo', 'https://example.com/', 'file:///tmp/repo'] as $url) {
            yield $url => [$url];
        }
    }

    public function testAcceptsAdministratorConfiguredPort(): void
    {
        $genericGitRepositoryUrlParser = new GenericGitRepositoryUrlParser(new OutboundUrlPolicy([
            'https://git.company.test:8443' => ['10.0.0.9'],
        ]));
        self::assertSame('https://git.company.test:8443/org/repo', $genericGitRepositoryUrlParser->parse('https://GIT.company.test:8443/org/repo.git')['clone_url']);
    }
}
