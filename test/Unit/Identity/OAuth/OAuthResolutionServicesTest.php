<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Application\Command\RegistryTokenCommandService;
use Packio\Identity\Application\Result\OAuthUserResolutionType;
use Packio\Identity\Application\Validator\PasswordValidator;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Adapter\RegistrationPolicyAdapter;
use Packio\Identity\Infrastructure\OAuth\OAuthAccountLinker;
use Packio\Identity\Infrastructure\OAuth\OAuthRegistrationPolicy;
use Packio\Identity\Infrastructure\OAuth\OAuthUserResolver;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(OAuthRegistrationPolicy::class)]
#[CoversClass(OAuthUserResolver::class)]
#[CoversClass(OAuthAccountLinker::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthResolutionServicesTest extends TestCase
{
    #[Test]
    public function missingEmailNeedsResolutionWithoutCreatingUser(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findByEmail');
        $users->expects(self::never())->method('save');
        $oAuthUserResolution = new OAuthUserResolver($users, $this->identityCommandService($users))->resolveUser(null, new OAuthUserData('123', '  ', null, null, null));
        self::assertSame(OAuthUserResolutionType::EmailRequired, $oAuthUserResolution->type);
        self::assertNull($oAuthUserResolution->user);
    }

    #[Test]
    public function danglingLinkFailsWithoutCreatingUser(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('save');
        $oAuthUserResolver = new OAuthUserResolver($users, $this->identityCommandService($users));
        $this->expectExceptionMessage('OAuth account references non-existent user');
        $oAuthUserResolver->resolveUser($this->account(), new OAuthUserData('123', null, null, null, null));
    }

    #[Test]
    public function externalInvitationRedirectCannotBypassClosedRegistration(): void
    {
        $settings = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $settings->method('registrationOpen')->willReturn(false);
        $organizations = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizations->expects(self::never())->method('findInvitationByToken');
        $oAuthRegistrationPolicy = new OAuthRegistrationPolicy($this->createMock(UserRepositoryInterface::class), new RegistrationPolicyAdapter($settings, $organizations));
        self::assertFalse($oAuthRegistrationPolicy->allowsRegistration(null, new OAuthUserData('123', 'user@example.test', null, null, null), 'https://evil.test/organization/invite/token'));
    }

    #[Test]
    public function alreadyLinkedAccountAllowsRegistrationWithoutEmailLookup(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('findByEmail');
        $oAuthRegistrationPolicy = new OAuthRegistrationPolicy($users, new RegistrationPolicyAdapter($this->createMock(ProjectSettingsApiFacadeInterface::class), $this->createMock(OrganizationApiFacadeInterface::class)));
        self::assertTrue($oAuthRegistrationPolicy->allowsRegistration($this->account(), new OAuthUserData('123', 'user@example.test', null, null, null), '/'));
    }

    #[Test]
    public function accountOwnedByAnotherUserIsNeverModified(): void
    {
        $accounts = $this->createMock(UserOAuthAccountRepositoryInterface::class);
        $accounts->method('findByProviderAndProviderUserId')->willReturn($this->account());
        $accounts->expects(self::never())->method('save');
        new OAuthAccountLinker($accounts)->persistOAuthAccount(
            'other-user',
            new OAuthUserData('123', null, null, null, null),
            OAuthProvider::Github,
            ['repo'],
        );
    }

    #[Test]
    public function updatingLinkClearsStaleScopes(): void
    {
        $account  = $this->account();
        $accounts = $this->createMock(UserOAuthAccountRepositoryInterface::class);
        $accounts->method('findByProviderAndProviderUserId')->willReturn($account);
        $accounts->expects(self::once())->method('save')->with($account);
        new OAuthAccountLinker($accounts)->persistOAuthAccount(
            'user',
            new OAuthUserData('123', null, null, null, null),
            OAuthProvider::Github,
        );
        self::assertSame([], $account->scopes());
    }

    private function identityCommandService(UserRepositoryInterface $userRepository): IdentityCommandService
    {
        return new IdentityCommandService(
            $userRepository,
            new ReflectionClass(RegistryTokenCommandService::class)->newInstanceWithoutConstructor(),
            $this->createStub(UserOAuthAccountRepositoryInterface::class),
            new PasswordValidator(),
        );
    }

    private function account(): UserOAuthAccount
    {
        return UserOAuthAccount::register('user', OAuthProvider::Github, '123', null, null, scopes: ['repo']);
    }
}
