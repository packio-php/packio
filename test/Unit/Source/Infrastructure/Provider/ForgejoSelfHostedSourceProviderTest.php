<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Provider;

use InvalidArgumentException;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Infrastructure\CodebergRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\Codeberg\CodebergProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\ForgejoSelfHosted\ForgejoSelfHostedSourceProvider;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(ForgejoSelfHostedSourceProvider::class)]
final class ForgejoSelfHostedSourceProviderTest extends TestCase
{
    public function testDescriptor(): void
    {
        $providerDescriptor = $this->provider()->descriptor();

        self::assertSame('forgejo_self_hosted', $providerDescriptor->name);
        self::assertSame('Forgejo Self-Hosted', $providerDescriptor->label);
        self::assertSame(['oauth2', 'token'], $providerDescriptor->authorizationStrategies);
    }

    public function testResourcesFetchesFromInstanceBaseUrl(): void
    {
        $resource = new ProviderResourceData(
            id: 'team/package',
            name: 'team/package',
            type: 'forgejo_self_hosted',
            url: 'https://forgejo.company.test/git/team/package',
            defaultBranch: 'main',
        );
        $fetcher = $this->createMock(CodebergProviderResourceFetcherInterface::class);
        $fetcher->expects(self::once())
            ->method('fetchResources')
            ->with('token', 'https://forgejo.company.test/git', 'forgejo_self_hosted')
            ->willReturn([$resource])
        ;

        self::assertSame([$resource], $this->provider($fetcher)->resources($this->authorization(), 'token'));
    }

    public function testResourcesFallsBackToAuthorizationConfiguration(): void
    {
        $fetcher = $this->createStub(CodebergProviderResourceFetcherInterface::class);
        $fetcher->method('fetchResources')->willReturn([]);

        $resources = $this->provider($fetcher)->resources($this->authorization([
            'resource_name' => 'team/package',
            'resource_url'  => 'https://forgejo.company.test/git/team/package',
        ]));

        self::assertCount(1, $resources);
        self::assertSame('team/package', $resources[0]->name);
    }

    public function testResourcesWithoutBaseUrlIsEmpty(): void
    {
        $fetcher = $this->createMock(CodebergProviderResourceFetcherInterface::class);
        $fetcher->expects(self::never())->method('fetchResources');

        $providerAuthorization = ProviderAuthorization::authorize(
            organizationId: 'org-1',
            provider: 'forgejo_self_hosted',
            authStrategy: 'token',
            configuration: [],
        );

        self::assertSame([], $this->provider($fetcher)->resources($providerAuthorization));
    }

    public function testRegisterSourceRejectsRepositoryFromDifferentProviderInstance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match the selected provider instance');

        $this->provider()->registerSource('org-1', $this->registration('https://other-forgejo.company.test/team/package'), $this->authorization());
    }

    public function testRegisterSourceRequiresProviderInstanceBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->provider()->registerSource('org-1', $this->registration('https://forgejo.company.test/git/team/package'));
    }

    public function testRegisterSourceStoresMatchingProviderInstance(): void
    {
        $source = $this->provider()->registerSource(
            'org-1',
            $this->registration('https://forgejo.company.test/git/team/package.git'),
            $this->authorization(),
        );

        self::assertSame('forgejo_self_hosted', $source->provider());
        self::assertSame('https://forgejo.company.test/git/team/package', $source->canonicalUrl());
        self::assertSame('instance-1', $source->providerInstanceId());
        $providerConfig = $source->providerConfig();
        self::assertSame('team', $providerConfig['owner'] ?? null);
        self::assertSame('package', $providerConfig['repository'] ?? null);
        self::assertSame('https://forgejo.company.test/git', $providerConfig['base_url'] ?? null);
        self::assertSame('instance-1', $providerConfig['provider_instance_id'] ?? null);
        self::assertNotSame('', $providerConfig['webhook_secret'] ?? '');
    }

    /**
     * @param array<string, string> $extra
     */
    private function authorization(array $extra = []): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorize(
            organizationId: 'org-1',
            provider: 'forgejo_self_hosted',
            authStrategy: 'token',
            configuration: [
                'base_url' => 'https://forgejo.company.test/git',
                ...$extra,
            ],
        );
        $providerAuthorization->assignProviderInstance('instance-1');
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        return $providerAuthorization;
    }

    private function registration(string $url): SourceRegistrationData
    {
        return new SourceRegistrationData(
            provider: 'forgejo_self_hosted',
            organizationId: 'org-1',
            resourceId: $url,
            name: 'team/package',
            type: 'forgejo_self_hosted',
            url: $url,
            defaultBranch: 'main',
            visibility: 'private',
        );
    }

    private function provider(
        ?CodebergProviderResourceFetcherInterface $codebergProviderResourceFetcher = null
    ): ForgejoSelfHostedSourceProvider {
        return new ForgejoSelfHostedSourceProvider(
            new CodebergRepositoryUrlParser(),
            $codebergProviderResourceFetcher ?? $this->createStub(CodebergProviderResourceFetcherInterface::class),
            new ProviderResourceFallbackFactory(),
            new SourceRegistrationFactory(),
            new WebhookSecretGenerator(),
        );
    }
}
