<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Provider;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Infrastructure\Provider\Gitlab\HttpGitlabProviderResourceFetcher;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(HttpGitlabProviderResourceFetcher::class)]
final class HttpGitlabProviderResourceFetcherTest extends TestCase
{
    public function testMapProjectsKeepsGitlabDefaultBranchWhenPresent(): void
    {
        $resources = $this->mapProjects([
            [
                'path_with_namespace' => 'vendor/package',
                'web_url'             => 'https://gitlab.com/vendor/package',
                'default_branch'      => 'master',
                'visibility'          => 'private',
            ],
        ]);

        self::assertCount(1, $resources);
        self::assertSame('master', $resources[0]->defaultBranch);
    }

    public function testMapProjectsDoesNotFallbackMissingDefaultBranchToMain(): void
    {
        $resources = $this->mapProjects([
            [
                'path_with_namespace' => 'vendor/package',
                'web_url'             => 'https://gitlab.com/vendor/package',
                'visibility'          => 'private',
            ],
        ]);

        self::assertCount(1, $resources);
        self::assertSame('', $resources[0]->defaultBranch);
    }

    /**
     * @param list<array<string, mixed>> $projects
     *
     * @return list<ProviderResourceData>
     */
    private function mapProjects(array $projects): array
    {
        $httpGitlabProviderResourceFetcher = new HttpGitlabProviderResourceFetcher(
            new BrandingConfig([]),
            new ProviderApiRequestor(new UpstreamHttpClient(new Client())),
        );
        $reflectionMethod = new ReflectionMethod($httpGitlabProviderResourceFetcher, 'mapProjects');

        return $reflectionMethod->invoke($httpGitlabProviderResourceFetcher, $projects);
    }
}
