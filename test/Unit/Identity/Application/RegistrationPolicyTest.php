<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Infrastructure\Adapter\RegistrationPolicyAdapter;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegistrationPolicyAdapter::class)]
final class RegistrationPolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('invitations')]
    public function closedRegistrationRequiresAPendingInvitation(?string $status, bool $allowed): void
    {
        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('registrationOpen')->willReturn(false);
        $organizations = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizations->expects(self::once())->method('findInvitationByToken')->with('token value')->willReturn(null === $status ? null : new OrganizationInvitationDto('id', 'org', 'admin', 'token value', 'member', $status));
        self::assertSame($allowed, new RegistrationPolicyAdapter($settings, $organizations)->allowsRegistration('/organization/invite/token%20value'));
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function invitations(): iterable
    {
        yield 'pending' => ['pending', true];

        yield 'accepted' => ['accepted', false];

        yield 'revoked' => ['revoked', false];

        yield 'missing' => [null, false];
    }

    #[Test]
    public function arbitraryRedirectDoesNotBypassClosedRegistration(): void
    {
        $settings = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $settings->method('registrationOpen')->willReturn(false);
        $organizations = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizations->expects(self::never())->method('findInvitationByToken');
        self::assertFalse(new RegistrationPolicyAdapter($settings, $organizations)->allowsRegistration('/organizations'));
    }
}
