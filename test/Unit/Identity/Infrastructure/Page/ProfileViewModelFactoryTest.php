<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Page;

use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Api\Dto\UserSignInDto;
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
            'profile'         => null,
            'oauth_accounts'  => [],
            'api_tokens'      => [],
            'sign_in_history' => [],
        ], new ProfileViewModelFactory()->create(null, [], [], []));
    }

    public function testSignInHistoryContractKeepsNulls(): void
    {
        $userDto = new UserDto('user', 'user@example.test', 'User', [], 'created');
        $result  = new ProfileViewModelFactory()->create($userDto, [], [], [
            new UserSignInDto('s1', 'github', 'GitHub', '2026-11-22T10:00:00+00:00', '203.0.113.5', 'Mozilla'),
            new UserSignInDto('s2', 'password', 'Password', '2026-11-21T10:00:00+00:00', null, null),
        ]);

        self::assertSame([
            [
                'id'           => 's1',
                'method'       => 'github',
                'method_label' => 'GitHub',
                'occurred_at'  => '2026-11-22T10:00:00+00:00',
                'ip'           => '203.0.113.5',
                'user_agent'   => 'Mozilla',
            ],
            [
                'id'           => 's2',
                'method'       => 'password',
                'method_label' => 'Password',
                'occurred_at'  => '2026-11-21T10:00:00+00:00',
                'ip'           => null,
                'user_agent'   => null,
            ],
        ], $result['sign_in_history']);
    }

    #[DataProvider('timestamps')]
    public function testProfileAndTokenContract(string $timestamp, ?string $expectedTimestamp): void
    {
        $userDto           = new UserDto('user', 'user@example.test', 'User', ['user', 'admin'], 'created', $timestamp, true);
        $apiAccessTokenDto = new ApiAccessTokenDto('token', 'user', 'Deploy', 'prefix', ['read'], 'created', $timestamp, $timestamp, $timestamp, $timestamp);

        self::assertSame([
            'profile'         => [
                'id'           => 'user',
                'email'        => 'user@example.test',
                'display_name' => 'User',
                'roles'        => ['user', 'admin'],
                'has_password' => true,
                'created_at'   => 'created',
                'updated_at'   => $expectedTimestamp,
            ],
            'oauth_accounts'  => [],
            'api_tokens'      => [[
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
            'sign_in_history' => [],
        ], new ProfileViewModelFactory()->create($userDto, [], [$apiAccessTokenDto], []));
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
        $result    = new ProfileViewModelFactory()->create($userDto, [new UserOAuthAccountResult('oauth', $provider, $username, $email)], [], []);

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
