<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Identity\Api\Exception\OAuthRefreshRejectedException;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(OAuthTokenResponseMapper::class)]
final class OAuthTokenResponseMapperTest extends TestCase
{
    /** @param array{status: int, body: null|array<int|string, mixed>} $response */
    #[Test]
    #[DataProvider('refreshFailures')]
    public function refreshFailureIsRejectionOnlyForRejectedRefreshToken(array $response, bool $rejected): void
    {
        try {
            new OAuthTokenResponseMapper()->mapRequiredAccessToken($response, 'invalid response', 'no access token returned');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($rejected, $runtimeException instanceof OAuthRefreshRejectedException);
        }
    }

    /** @return array<string, array{array{status: int, body: null|array<int|string, mixed>}, bool}> */
    public static function refreshFailures(): array
    {
        return [
            'invalid_grant 400'        => [[
                'status' => 400,
                'body'   => [
                    'error' => 'invalid_grant',
                ],
            ], true],
            'bad_refresh_token 200'    => [[
                'status' => 200,
                'body'   => [
                    'error' => 'bad_refresh_token',
                ],
            ], true],
            'unauthorized_client 400'  => [[
                'status' => 400,
                'body'   => [
                    'error' => 'unauthorized_client',
                ],
            ], true],
            'invalid_client 401'       => [[
                'status' => 401,
                'body'   => [
                    'error' => 'invalid_client',
                ],
            ], true],
            'empty 4xx body'           => [[
                'status' => 400,
                'body'   => [],
            ], true],
            'non-JSON 4xx body'        => [[
                'status' => 400,
                'body'   => null,
            ], true],
            '200 without access token' => [[
                'status' => 200,
                'body'   => [
                    'token_type' => 'bearer',
                ],
            ], true],
            'network status 0'         => [[
                'status' => 0,
                'body'   => null,
            ], false],
            'server error 503'         => [[
                'status' => 503,
                'body'   => [
                    'error' => 'temporarily_unavailable',
                ],
            ], false],
            'rate limited 429'         => [[
                'status' => 429,
                'body'   => [
                    'error' => 'slow_down',
                ],
            ], false],
            'temporarily_unavailable'  => [[
                'status' => 400,
                'body'   => [
                    'error' => 'temporarily_unavailable',
                ],
            ], false],
            'server_error 200'         => [[
                'status' => 200,
                'body'   => [
                    'error' => 'server_error',
                ],
            ], false],
        ];
    }

    #[Test]
    public function oAuthErrorIsRejectionOnlyForRefreshCalls(): void
    {
        $response = [
            'status' => 400,
            'body'   => [
                'error'             => 'invalid_grant',
                'error_description' => 'expired',
            ],
        ];

        try {
            new OAuthTokenResponseMapper()->mapOAuthError($response, 'invalid response', 'Codeberg OAuth error', 'failed');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $runtimeException) {
            self::assertNotInstanceOf(OAuthRefreshRejectedException::class, $runtimeException);
            self::assertSame('Codeberg OAuth error (invalid_grant): expired', $runtimeException->getMessage());
        }

        $this->expectException(OAuthRefreshRejectedException::class);
        $this->expectExceptionMessage('Codeberg OAuth error (invalid_grant): expired');

        new OAuthTokenResponseMapper()->mapOAuthError($response, 'invalid response', 'Codeberg OAuth error', 'failed', refresh: true);
    }
}
