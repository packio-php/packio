<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Packio\Registry\Infrastructure\BitbucketDistArchiveFetcher;
use Packio\Registry\Infrastructure\CodebergDistArchiveFetcher;
use Packio\Registry\Infrastructure\DistArchiveFetcher;
use Packio\Registry\Infrastructure\GenericGitDistArchiveFetcher;
use Packio\Registry\Infrastructure\GitlabDistArchiveFetcher;
use Packio\Registry\Infrastructure\GitlabSelfHostedDistArchiveFetcher;
use Packio\Registry\Infrastructure\HttpDistArchiveDownloader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(DistArchiveFetcher::class)]
final class DistArchiveFetcherTest extends TestCase
{
    #[Test]
    public function forgejoSelfHostedDownloadsFromInstanceBaseUrl(): void
    {
        $urls = $this->fetchUrls('forgejo_self_hosted', 'https://forgejo.example.test');

        self::assertSame(['https://forgejo.example.test/api/v1/repos/vendor/package/archive/v1.0.0.zip'], $urls);
    }

    #[Test]
    public function codebergIgnoresBaseUrlAndStaysOnCodebergOrg(): void
    {
        $urls = $this->fetchUrls('codeberg', 'https://evil.example.test');

        self::assertSame(['https://codeberg.org/api/v1/repos/vendor/package/archive/v1.0.0.zip'], $urls);
    }

    /**
     * @return list<string>
     */
    private function fetchUrls(string $provider, string $baseUrl): array
    {
        $urls         = [];
        $handlerStack = HandlerStack::create(new MockHandler([new Response(200, [], 'zip')]));
        $handlerStack->push(Middleware::tap(static function(RequestInterface $request) use (&$urls): void {
            $urls[] = (string) $request->getUri();
        }));

        $brandingConfig = new BrandingConfig([
            'http_user_agent' => 'Packio/Tests',
        ]);
        $downloader     = new HttpDistArchiveDownloader(new UpstreamHttpClient(new Client([
            'handler' => $handlerStack,
        ])));
        $genericGit     = new GenericGitDistArchiveFetcher();

        new DistArchiveFetcher(
            $brandingConfig,
            new GitlabDistArchiveFetcher($genericGit),
            new GitlabSelfHostedDistArchiveFetcher($brandingConfig, $downloader),
            new BitbucketDistArchiveFetcher($genericGit),
            new CodebergDistArchiveFetcher($brandingConfig, $downloader),
            $genericGit,
            $downloader,
        )->fetchArchive($provider, 'vendor', 'package', 'v1.0.0', 'token', $baseUrl);

        return $urls;
    }
}
