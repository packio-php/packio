<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Page;

use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Application\Result\UserOAuthAccountResult;
use Packio\Identity\Infrastructure\Page\ProfileViewModelFactory;
use Packio\Shared\Api\Dto\UserDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileViewModelFactory::class)]
final class ProfileViewModelFactoryTest extends TestCase
{
    public function testEmptyProfileContract(): void
    {
        self::assertSame([
            'profile'        => null,
            'oauth_accounts' => [],
            'api_tokens'     => [],
        ], new ProfileViewModelFactory()->create(null, [], []));
    }

    #[DataProvider('timestamps')]
    public function testProfileAndTokenContract(string $timestamp, ?string $expectedTimestamp): void
    {
        $userDto           = new UserDto('user', 'user@example.test', 'User', ['user', 'admin'], 'created', $timestamp, true);
        $apiAccessTokenDto = new ApiAccessTokenDto('token', 'user', 'Deploy', 'prefix', ['read'], 'created', $timestamp, $timestamp, $timestamp, $timestamp);

        self::assertSame([
            'profile'        => [
                'id'           => 'user',
                'email'        => 'user@example.test',
                'display_name' => 'User',
                'roles'        => ['user', 'admin'],
                'has_password' => true,
                'created_at'   => 'created',
                'updated_at'   => $expectedTimestamp,
            ],
            'oauth_accounts' => [],
            'api_tokens'     => [[
                'id'           => 'token',
                'name'         => 'Deploy',
                'token_prefix' => 'prefix',
                'scopes'       => ['read'],
                'created_at'   => 'created',
                'updated_at'   => $expectedTimestamp,
                'last_used_at' => $expectedTimestamp,
                'expires_at'   => $expectedTimestamp,
                'revoked_at'   => $expectedTimestamp,
                'revoked'      => null !== $expectedTimestamp,
            ]],
        ], new ProfileViewModelFactory()->create($userDto, [], [$apiAccessTokenDto]));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function timestamps(): iterable
    {
        yield 'empty timestamps become null' => ['', null];

        yield 'populated timestamps are preserved' => ['2026-09-19T12:00:00+00:00', '2026-09-19T12:00:00+00:00'];
    }

    #[DataProvider('oauthLabels')]
    public function testOAuthAccountLabelFallbacks(
        string $provider,
        ?string $username,
        ?string $email,
        string $providerLabel,
        string $accountLabel
    ): void {
        $userDto   = new UserDto('user', 'user@example.test', 'User', [], 'created');
        $result    = new ProfileViewModelFactory()->create($userDto, [new UserOAuthAccountResult('oauth', $provider, $username, $email)], []);

        self::assertSame([[
            'id'                => 'oauth',
            'provider'          => $provider,
            'provider_label'    => $providerLabel,
            'account_label'     => $accountLabel,
            'provider_email'    => $email,
            'provider_username' => $username,
        ]], $result['oauth_accounts']);
        self::assertFalse($result['profile']['has_password']);
    }

    /** @return iterable<string, array{string, ?string, ?string, string, string}> */
    public static function oauthLabels(): iterable
    {
        yield 'username wins' => ['github', 'username', 'email@example.test', 'GitHub', 'username'];

        yield 'email fallback' => ['github', null, 'email@example.test', 'GitHub', 'email@example.test'];

        yield 'provider fallback' => ['github', null, null, 'GitHub', 'GitHub'];

        yield 'unknown provider' => ['custom', null, null, 'custom', 'custom'];

        yield 'empty username preserved' => ['github', '', 'email@example.test', 'GitHub', ''];
    }
}
