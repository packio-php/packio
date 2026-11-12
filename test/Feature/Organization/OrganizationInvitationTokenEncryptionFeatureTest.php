<?php

declare(strict_types=1);

namespace PackioTest\Feature\Organization;

use Common\Branding\BrandingConfig;
use Common\Security\SodiumSecretEncryptor;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\Dto\OrganizationInvitationDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Cli\OrganizationInvitationsEncryptCommand;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function hash;
use function random_bytes;
use function rawurlencode;
use function rtrim;

final class OrganizationInvitationTokenEncryptionFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itStoresNewInvitationTokensEncryptedAndHashedAndShowsTheSameLink(): void
    {
        [$owner, $organization]                = $this->ownerWithOrganization();
        $organizationInvitationDto             = $this->createInvitation($organization, $owner);

        $row = $this->storedRow($organizationInvitationDto->id);
        self::assertStringStartsWith('orgi_', $organizationInvitationDto->token);
        self::assertStringStartsWith('secretbox:v1:', $row['token']);
        self::assertNotSame($organizationInvitationDto->token, $row['token']);
        self::assertSame(hash('sha256', $organizationInvitationDto->token), $row['token_hash']);

        $this->logIn($owner->email);
        self::assertSame([$this->acceptUrl($organizationInvitationDto->token)], $this->memberPageAcceptUrls($organization));
    }

    #[Test]
    public function itAcceptsALegacyPlaintextInvitationAndEncryptsItWithTheCommand(): void
    {
        [$owner, $organization]                = $this->ownerWithOrganization();
        $organizationInvitationDto             = $this->createInvitation($organization, $owner);
        $legacyToken                           = 'orgi_legacy' . bin2hex(random_bytes(12));
        // Same SQL the migration uses to backfill token_hash for rows written before it.
        $this->database()->execute(
            "UPDATE organization_invitations SET token = ?, token_hash = encode(sha256(convert_to(?, 'UTF8')), 'hex') WHERE uuid = ?",
            [$legacyToken, $legacyToken, $organizationInvitationDto->id],
        );
        $this->rebuildContainer();

        $this->logIn($owner->email);
        self::assertSame([$this->acceptUrl($legacyToken)], $this->memberPageAcceptUrls($organization));

        self::assertStringContainsString('dry_run=1', $this->runEncryptCommand([
            '--dry-run' => true,
        ]));
        self::assertSame($legacyToken, $this->storedRow($organizationInvitationDto->id)['token']);

        self::assertStringContainsString('encrypted=1', $this->runEncryptCommand());
        $row = $this->storedRow($organizationInvitationDto->id);
        self::assertStringStartsWith('secretbox:v1:', $row['token']);
        self::assertSame(hash('sha256', $legacyToken), $row['token_hash']);
        self::assertStringContainsString('encrypted=0', $this->runEncryptCommand());
        self::assertSame($row['token'], $this->storedRow($organizationInvitationDto->id)['token']);
        $this->rebuildContainer();

        $userDto = $this->createVerifiedUser('legacy-invitee');
        $this->clearCookies();
        $this->logIn($userDto->email);
        $this->withCsrf()
            ->post('/organization/invite/' . $legacyToken . '/accept')
            ->assertRedirect('/organization/' . $organization->slug . '/packages')
        ;

        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        self::assertNotNull($organizations->findMembership($organization->id, $userDto->id));
        self::assertSame('accepted', $organizations->findInvitationByToken($legacyToken)?->status);
    }

    #[Test]
    public function itShowsAnInvitationWhoseTokenCannotBeDecryptedWithoutALink(): void
    {
        [$owner, $organization]                = $this->ownerWithOrganization();
        $organizationInvitationDto             = $this->createInvitation($organization, $owner);
        $this->database()->execute(
            'UPDATE organization_invitations SET token = ? WHERE uuid = ?',
            [new SodiumSecretEncryptor('rotated-away-key')->encrypt('orgi_old'), $organizationInvitationDto->id],
        );
        $this->rebuildContainer();

        $this->logIn($owner->email);
        $invitations = $this->memberPage($organization)['invitations'] ?? [];

        self::assertCount(1, $invitations);
        self::assertSame($organizationInvitationDto->id, $invitations[0]['id'] ?? null);
        self::assertArrayNotHasKey('accept_url', $invitations[0]);
        self::assertArrayNotHasKey('token', $invitations[0]);
    }

    #[Test]
    public function itAcceptsAnInvitationWhoseStoredTokenWasEncryptedWithAnotherKey(): void
    {
        [$owner, $organization]    = $this->ownerWithOrganization();
        $organizationInvitationDto = $this->createInvitation($organization, $owner);
        $linkToken                 = 'orgi_rotated' . bin2hex(random_bytes(12));
        $this->database()->execute(
            'UPDATE organization_invitations SET token = ?, token_hash = ? WHERE uuid = ?',
            [new SodiumSecretEncryptor('rotated-away-key')->encrypt($linkToken), hash('sha256', $linkToken), $organizationInvitationDto->id],
        );
        $this->rebuildContainer();

        $userDto = $this->createVerifiedUser('rotated-invitee');
        $this->clearCookies();
        $this->logIn($userDto->email);

        $testResponse = $this->get('/organization/invite/' . $linkToken, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/InviteAccept');
        self::assertSame($linkToken, $testResponse->json()['props']['invitation']['token'] ?? null);

        $this->withCsrf()
            ->post('/organization/invite/' . $linkToken . '/accept')
            ->assertRedirect('/organization/' . $organization->slug . '/packages')
        ;

        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        self::assertNotNull($organizations->findMembership($organization->id, $userDto->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPage(OrganizationDto $organizationDto): array
    {
        $testResponse = $this->get('/organization/' . $organizationDto->slug . '/members', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200)->assertInertiaComponent('Organization/Members');

        return $testResponse->json()['props'];
    }

    /**
     * @return list<mixed>
     */
    private function memberPageAcceptUrls(OrganizationDto $organizationDto): array
    {
        $urls = [];
        foreach ($this->memberPage($organizationDto)['invitations'] ?? [] as $invitation) {
            $urls[] = $invitation['accept_url'] ?? null;
        }

        return $urls;
    }

    private function acceptUrl(string $token): string
    {
        $brandingConfig = $this->getService(BrandingConfig::class);
        self::assertInstanceOf(BrandingConfig::class, $brandingConfig);

        return rtrim($brandingConfig->appBaseUrl(), '/') . '/organization/invite/' . rawurlencode($token);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runEncryptCommand(array $input = []): string
    {
        $command = $this->getService(OrganizationInvitationsEncryptCommand::class);
        self::assertInstanceOf(OrganizationInvitationsEncryptCommand::class, $command);
        $commandTester = new CommandTester($command);
        self::assertSame(0, $commandTester->execute($input));

        return $commandTester->getDisplay();
    }

    /**
     * @return array{0: UserDto, 1: OrganizationDto}
     */
    private function ownerWithOrganization(): array
    {
        $userDto         = $this->createVerifiedUser('token-owner');
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);

        return [$userDto, $organizations->registerOrganization('invite-token-' . bin2hex(random_bytes(4)), $userDto->id)];
    }

    private function createInvitation(OrganizationDto $organizationDto, UserDto $userDto): OrganizationInvitationDto
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);

        return $organizations->createInvitation($organizationDto->id, $userDto->id);
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);
        $userDto = $identity->createUser($prefix . '-' . bin2hex(random_bytes(6)) . '@example.test', 'Verified User', self::PASSWORD);
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    /**
     * @return array{token: string, token_hash: string}
     */
    private function storedRow(string $invitationId): array
    {
        $rows = $this->database()->query(
            'SELECT token, token_hash FROM organization_invitations WHERE uuid = ?',
            [$invitationId],
        )->fetchAll();

        return [
            'token'      => (string) ($rows[0]['token'] ?? ''),
            'token_hash' => (string) ($rows[0]['token_hash'] ?? ''),
        ];
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
