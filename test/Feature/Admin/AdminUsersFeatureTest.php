<?php

declare(strict_types=1);

namespace PackioTest\Feature\Admin;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_column;
use function bin2hex;
use function random_bytes;
use function rawurlencode;
use function ucfirst;

final class AdminUsersFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itReturnsCanonicalFiltersAndOnlyTheFilteredUserPage(): void
    {
        $userDto  = $this->createUser('admin', ['admin']);
        $active   = $this->createUser('active');
        $banned   = $this->createUser('banned');

        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $identity->banUser($banned->id, $userDto->id, 'Feature test moderation.');

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/users?status=banned&search=' . rawurlencode($banned->email) . '&page=9&per_page=999', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse
            ->assertStatus(200)
            ->assertInertiaComponent('Admin/Users')
        ;

        $props = $testResponse->json()['props'] ?? [];
        self::assertSame([
            'search'   => $banned->email,
            'status'   => 'banned',
            'page'     => 1,
            'per_page' => 100,
        ], $props['filters'] ?? null);
        self::assertSame(1, $props['pagination']['total'] ?? null);
        self::assertSame(1, $props['pagination']['current_page'] ?? null);
        self::assertSame(100, $props['pagination']['per_page'] ?? null);
        self::assertSame([$banned->id], array_column($props['users'] ?? [], 'id'));
        self::assertNotContains($active->id, array_column($props['users'] ?? [], 'id'));
    }

    #[Test]
    public function itReturnsOnlyTheRequestedPageAndItsMetadata(): void
    {
        $userDto = $this->createUser('admin', ['admin']);
        for ($index = 0; $index < 4; ++$index) {
            $this->createUser('member-' . $index);
        }

        $this->logIn($userDto->email);

        $testResponse = $this->get('/admin/users?page=2&per_page=2', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $props = $testResponse->json()['props'] ?? [];
        self::assertCount(2, $props['users'] ?? []);
        self::assertSame(5, $props['pagination']['total'] ?? null);
        self::assertSame(2, $props['pagination']['current_page'] ?? null);
        self::assertSame(2, $props['pagination']['per_page'] ?? null);
        self::assertTrue($props['pagination']['has_previous_pages'] ?? false);
        self::assertTrue($props['pagination']['has_more_pages'] ?? false);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $prefix, array $roles = ['user']): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
            $roles,
        );
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
}
