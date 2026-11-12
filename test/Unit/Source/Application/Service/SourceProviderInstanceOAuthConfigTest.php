<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceProviderInstanceOAuthConfig::class)]
final class SourceProviderInstanceOAuthConfigTest extends TestCase
{
    #[Test]
    public function buildsConfigForGitlabSelfHostedInstance(): void
    {
        $secretEncryptor = $this->createStub(SecretEncryptorInterface::class);
        $secretEncryptor->method('decrypt')->willReturnMap([['encrypted-client-secret', 'client-secret']]);

        $sourceProviderInstanceOAuthConfig               = $this->createConfig($secretEncryptor);
        $sourceProviderInstance                          = $this->createInstance('encrypted-client-secret');

        self::assertSame([
            'base_url'      => 'https://gitlab.company.test',
            'client_id'     => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_url'  => 'https://packio.test/auth/oauth/gitlab_self_hosted/source-callback',
            'source_scopes' => ['read_user', 'api', 'read_repository'],
        ], $sourceProviderInstanceOAuthConfig->forInstance($sourceProviderInstance));
        self::assertSame(['read_user', 'api', 'read_repository'], $sourceProviderInstanceOAuthConfig->scopes($sourceProviderInstance));
    }

    #[Test]
    public function returnsNullWithoutClientSecret(): void
    {
        $sourceProviderInstanceOAuthConfig = $this->createConfig($this->createStub(SecretEncryptorInterface::class));

        self::assertNull($sourceProviderInstanceOAuthConfig->forInstance($this->createInstance(null)));
    }

    private function createConfig(SecretEncryptorInterface $secretEncryptor): SourceProviderInstanceOAuthConfig
    {
        return new SourceProviderInstanceOAuthConfig(
            new SourceProviderInstanceSecrets($secretEncryptor),
            new BrandingConfig([
                'app_url' => 'https://packio.test/',
            ]),
        );
    }

    private function createInstance(?string $clientSecret): SourceProviderInstance
    {
        return SourceProviderInstance::create(
            organizationId: 'org-uuid',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: $clientSecret,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
    }
}
