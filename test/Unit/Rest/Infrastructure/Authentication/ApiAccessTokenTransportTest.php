<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Authentication;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Packio\Rest\Infrastructure\Authentication\ApiAccessTokenTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Token\AuthToken;

#[CoversClass(ApiAccessTokenTransport::class)]
final class ApiAccessTokenTransportTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('credentials')]
    public function fetchesExpectedCredential(array $headers, ?string $expected): void
    {
        $request = new ServerRequest();
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        self::assertSame($expected, new ApiAccessTokenTransport()->fetch($request));
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: ?string}>
     */
    public static function credentials(): iterable
    {
        yield 'case-insensitive bearer scheme' => [[
            'Authorization'                  => 'bEaReR api-token',
        ], 'api-token'];

        yield 'bearer whitespace is trimmed' => [[
            'Authorization'                                  => ' Bearer   api-token  ',
        ], 'api-token'];

        yield 'legacy header is accepted' => [[
            'X-API-TOKEN'                    => 'api-token',
        ], 'api-token'];

        yield 'valid bearer has priority over legacy header' => [
            [
                'Authorization' => 'Bearer bearer-token',
                'X-API-TOKEN'   => 'legacy-token',
            ],
            'bearer-token',
        ];

        yield 'malformed authorization falls back to legacy header' => [
            [
                'Authorization' => 'Token malformed',
                'X-API-TOKEN'   => 'legacy-token',
            ],
            'legacy-token',
        ];

        yield 'empty credentials are absent' => [[
            'Authorization' => 'Bearer   ',
            'X-API-TOKEN'   => '   ',
        ], null];
    }

    #[Test]
    public function attachesAndDetachesStandardBearerHeader(): void
    {
        $apiAccessTokenTransport = new ApiAccessTokenTransport();
        $authToken               = new AuthToken('token-id', 'api_access_token', []);

        $response = $apiAccessTokenTransport->attach(new Response(), $authToken);

        self::assertSame('Bearer token-id', $response->getHeaderLine('Authorization'));
        self::assertSame('', $apiAccessTokenTransport->detach($response)->getHeaderLine('Authorization'));
    }
}
