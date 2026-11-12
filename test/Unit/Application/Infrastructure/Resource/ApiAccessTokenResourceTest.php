<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Resource;

use Packio\Application\Infrastructure\Resource\ApiAccessTokenResource;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiAccessTokenResource::class)]
final class ApiAccessTokenResourceTest extends TestCase
{
    #[DataProvider('revocationStates')]
    public function testPublicTokenContract(string $revokedAt, ?string $expectedRevokedAt, bool $revoked): void
    {
        $apiAccessTokenDto = new ApiAccessTokenDto(
            id: 'token-id',
            userId: 'private-user-id',
            name: 'Automation',
            tokenPrefix: 'packio_api_prefix',
            scopes: ['read', 'write'],
            createdAt: '',
            updatedAt: '2026-09-19T12:00:00+00:00',
            lastUsedAt: '',
            expiresAt: '2027-01-01T00:00:00+00:00',
            revokedAt: $revokedAt,
        );

        self::assertSame([
            'id'           => 'token-id',
            'name'         => 'Automation',
            'token_prefix' => 'packio_api_prefix',
            'scopes'       => ['read', 'write'],
            'created_at'   => '',
            'updated_at'   => '2026-09-19T12:00:00+00:00',
            'last_used_at' => null,
            'expires_at'   => '2027-01-01T00:00:00+00:00',
            'revoked_at'   => $expectedRevokedAt,
            'revoked'      => $revoked,
        ], ApiAccessTokenResource::fromDto($apiAccessTokenDto));
    }

    /** @return iterable<string, array{string, ?string, bool}> */
    public static function revocationStates(): iterable
    {
        yield 'active' => ['', null, false];

        yield 'revoked' => ['2026-09-19T13:00:00+00:00', '2026-09-19T13:00:00+00:00', true];
    }
}
