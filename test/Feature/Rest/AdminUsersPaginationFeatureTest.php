<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;
use function rawurlencode;

final class AdminUsersPaginationFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itPaginatesAdminUsersAndPreservesCanonicalFiltersInLinks(): void
    {
        $userDto  = $this->createVerifiedUser('admin', ['user', 'admin']);
        $prefix   = 'rest-user-' . bin2hex(random_bytes(4));
        $this->createVerifiedUser($prefix . '-a');
        $this->createVerifiedUser($prefix . '-b');
        $this->createVerifiedUser($prefix . '-c');

        $testResponse = $this->get('/api/v1/admin/users?search=' . rawurlencode($prefix) . '&status=active&page=2&per_page=1&sort=created_at&direction=desc', $this->adminHeaders($userDto));
        $testResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 2)
            ->assertJsonPath('meta.to', 2)
        ;

        self::assertCount(1, $testResponse->json()['data'] ?? []);
        self::assertSame(
            'https://localhost/api/v1/admin/users?search=' . rawurlencode($prefix) . '&status=active&page=1&per_page=1',
            $testResponse->json()['links']['first'] ?? null,
        );
        self::assertSame(
            'https://localhost/api/v1/admin/users?search=' . rawurlencode($prefix) . '&status=active&page=3&per_page=1',
            $testResponse->json()['links']['next'] ?? null,
        );
        self::assertStringNotContainsString('sort=', $testResponse->json()['links']['first'] ?? '');
        self::assertStringNotContainsString('direction=', $testResponse->json()['links']['first'] ?? '');
    }

    #[Test]
    public function itNormalizesMalformedAdminUserPaginationAndFilters(): void
    {
        $userDto = $this->createVerifiedUser('admin', ['user', 'admin']);
        $this->createVerifiedUser('normalization-a');
        $this->createVerifiedUser('normalization-b');

        $testResponse = $this->get('/api/v1/admin/users?page[]=2&per_page=999&search[]=ignored&status[]=banned', $this->adminHeaders($userDto));
        $testResponse->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 3)
        ;

        self::assertSame(
            'https://localhost/api/v1/admin/users?page=1&per_page=100',
            $testResponse->json()['links']['first'] ?? null,
        );
    }

    #[Test]
    public function itRequiresBothAdminRoleAndAdminScopeForThePaginatedEndpoint(): void
    {
        $userDto   = $this->createVerifiedUser('admin', ['user', 'admin']);
        $regular   = $this->createVerifiedUser('regular');

        $this->get('/api/v1/admin/users', $this->headersForToken($this->issueToken($userDto, ['read'])))
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'API token scope is not sufficient.')
        ;

        $this->get('/api/v1/admin/users', $this->headersForToken($this->rawIssueToken($regular, ['admin'])))
            ->assertStatus(403)
        ;
    }

    /**
     * @param list<string> $roles
     */
    private function createVerifiedUser(string $prefix, array $roles = ['user']): UserDto
    {
        $identity = $this->identity();
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'REST API User',
            self::PASSWORD,
            $roles,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        $verified = $identity->findUserById($userDto->id);
        self::assertInstanceOf(UserDto::class, $verified);

        return $verified;
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(UserDto $userDto): array
    {
        return $this->headersForToken($this->issueToken($userDto, ['admin']));
    }

    /**
     * @param list<string> $scopes
     */
    private function issueToken(UserDto $userDto, array $scopes): string
    {
        return $this->identity()
            ->issueApiAccessTokenForUser($userDto->id, 'Admin users pagination', $scopes)
            ->plainTextToken
        ;
    }

    /**
     * @param list<string> $scopes
     */
    private function rawIssueToken(UserDto $userDto, array $scopes): string
    {
        $commandService = $this->getService(ApiAccessTokenCommandService::class);
        self::assertInstanceOf(ApiAccessTokenCommandService::class, $commandService);

        return $commandService
            ->issueForUser($userDto->id, 'Raw admin users pagination', $scopes)
            ->plainTextToken
        ;
    }

    /**
     * @return array<string, string>
     */
    private function headersForToken(string $token): array
    {
        return [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }
}
