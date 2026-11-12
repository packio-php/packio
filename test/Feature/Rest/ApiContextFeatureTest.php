<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_any;
use function bin2hex;
use function random_bytes;

final class ApiContextFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function apiTokenUserTakesPrecedenceOverBrowserSessionUser(): void
    {
        $userDto         = $this->createVerifiedUser('session');
        $apiUser         = $this->createVerifiedUser('api-token');
        self::assertNotSame($userDto->id, $apiUser->id);

        $organizationDto    = $this->organization()->registerOrganization('API Context ' . bin2hex(random_bytes(4)), $apiUser->id);
        $plainTextToken     = $this->identity()
            ->issueApiAccessTokenForUser($apiUser->id, 'Context API token', ['write'])
            ->plainTextToken
        ;
        $updatedName     = 'API Context Updated ' . bin2hex(random_bytes(4));

        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug, [
            'name'             => $updatedName,
            'anonymous_access' => true,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $plainTextToken,
        ], true)
            ->assertStatus(200)
            ->assertJsonPath('data.name', $updatedName)
            ->assertJsonPath('data.anonymous_access', true)
        ;

        $storedOrganization = $this->organization()->findOrganization($organizationDto->id);
        self::assertInstanceOf(OrganizationDto::class, $storedOrganization);
        self::assertSame($updatedName, $storedOrganization->name);

        $auditEntries = $this->audit()->recentByOrganization($organizationDto->id, 10);
        self::assertTrue(array_any($auditEntries, static fn ($auditEntryDto): bool => 'api.v1.organizations.update' === $auditEntryDto->event
            && 'PATCH' === ($auditEntryDto->context['method'] ?? null)
            && 200 === ($auditEntryDto->context['status'] ?? null)
            && $apiUser->id === ($auditEntryDto->context['user_id'] ?? null)));
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        $identity    = $this->identity();
        $userDto     = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'API Context User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        $verified = $identity->findUserById($userDto->id);
        self::assertInstanceOf(UserDto::class, $verified);

        return $verified;
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }

    private function organization(): OrganizationApiFacadeInterface
    {
        $organization = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organization);

        return $organization;
    }

    private function audit(): AuditApiFacadeInterface
    {
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $audit);

        return $audit;
    }
}
