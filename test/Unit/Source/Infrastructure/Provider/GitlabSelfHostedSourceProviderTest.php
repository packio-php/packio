<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Provider;

use InvalidArgumentException;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Infrastructure\GitlabSelfHostedRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\GitlabSelfHosted\GitlabSelfHostedProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\GitlabSelfHosted\GitlabSelfHostedSourceProvider;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(GitlabSelfHostedSourceProvider::class)]
#[AllowMockObjectsWithoutExpectations]
final class GitlabSelfHostedSourceProviderTest extends TestCase
{
    public function testRegisterSourceRejectsRepositoryFromDifferentProviderInstance(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize(
            organizationId: 'org-1',
            provider: 'gitlab_self_hosted',
            authStrategy: 'token',
            configuration: [
                'base_url' => 'https://gitlab.company.test',
            ],
        );
        $providerAuthorization->assignProviderInstance('instance-1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match the selected provider instance');

        $this->provider()->registerSource(
            'org-1',
            new SourceRegistrationData(
                provider: 'gitlab_self_hosted',
                organizationId: 'org-1',
                resourceId: 'https://other-gitlab.company.test/group/package',
                name: 'group/package',
                type: 'gitlab_self_hosted',
                url: 'https://other-gitlab.company.test/group/package',
                defaultBranch: 'main',
                visibility: 'private',
            ),
            $providerAuthorization,
        );
    }

    public function testRegisterSourceStoresMatchingProviderInstance(): void
    {
        $providerAuthorization = ProviderAuthorization::authorize(
            organizationId: 'org-1',
            provider: 'gitlab_self_hosted',
            authStrategy: 'token',
            configuration: [
                'base_url' => 'https://gitlab.company.test',
            ],
        );
        $providerAuthorization->assignProviderInstance('instance-1');
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));

        $source = $this->provider()->registerSource(
            'org-1',
            new SourceRegistrationData(
                provider: 'gitlab_self_hosted',
                organizationId: 'org-1',
                resourceId: 'https://gitlab.company.test/group/package',
                name: 'group/package',
                type: 'gitlab_self_hosted',
                url: 'https://gitlab.company.test/group/package',
                defaultBranch: 'main',
                visibility: 'private',
            ),
            $providerAuthorization,
        );

        self::assertSame('instance-1', $source->providerInstanceId());
        self::assertSame('instance-1', $source->providerConfig()['provider_instance_id'] ?? null);
        self::assertSame('https://gitlab.company.test', $source->providerConfig()['base_url'] ?? null);
    }

    private function provider(): GitlabSelfHostedSourceProvider
    {
        return new GitlabSelfHostedSourceProvider(
            new GitlabSelfHostedRepositoryUrlParser(),
            $this->createMock(GitlabSelfHostedProviderResourceFetcherInterface::class),
            new ProviderResourceFallbackFactory(),
            new SourceRegistrationFactory(),
            new WebhookSecretGenerator(),
        );
    }
}
