<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Http;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestPresentationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestPresentationPolicy::class)]
final class RequestPresentationPolicyTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('wantsJsonMatrix')]
    public function testWantsJsonMatrix(string $path, string $method, array $headers, bool $expectedWantsJson): void
    {
        $serverRequest = new ServerRequest([], [], $path, $method);
        foreach ($headers as $name => $value) {
            $serverRequest = $serverRequest->withHeader($name, $value);
        }

        self::assertSame($expectedWantsJson, new RequestPresentationPolicy()->wantsJson($serverRequest));
    }

    /**
     * @return array<string, array{string, string, array<string, string>, bool}>
     */
    public static function wantsJsonMatrix(): array
    {
        return [
            'plain web request'                                   => ['/settings', 'GET', [], false],
            'inertia request with web path'                       => ['/settings', 'GET', [
                'X-Inertia' => 'true',
            ], false],
            'inertia request with any inertia header value'       => ['/settings', 'GET', [
                'X-Inertia' => '1',
            ], false],
            'inertia request with json accept still web'          => ['/settings', 'GET', [
                'X-Inertia' => 'true',
                'Accept'    => 'application/json',
            ], false],
            'json accept header without inertia'                  => ['/settings', 'GET', [
                'Accept' => 'application/json',
            ], true],
            'text/json accept header without inertia'             => ['/settings', 'GET', [
                'Accept' => 'text/json',
            ], true],
            'accept header with json among alternatives'          => ['/settings', 'GET', [
                'Accept' => 'text/html, application/json',
            ], true],
            'accept header is case insensitive'                   => ['/settings', 'GET', [
                'Accept' => 'Application/JSON',
            ], true],
            'api path wins over inertia header'                   => ['/api/v1/organizations', 'GET', [
                'X-Inertia' => 'true',
            ], true],
            'api path wins over inertia with json accept'         => ['/api/v1/example', 'POST', [
                'X-Inertia' => 'true',
                'Accept'    => 'application/json',
            ], true],
            'api path without any headers'                        => ['/api/v1/example', 'GET', [], true],
            'api root path'                                       => ['/api', 'GET', [], true],
            'web path merely containing api substring is not api' => ['/organization/api-keys', 'GET', [], false],
            'exact api prefix match is required'                  => ['/apifoo', 'GET', [], false],
        ];
    }

    #[Test]
    public function isApiRequestMatchesApiPaths(): void
    {
        $requestPresentationPolicy = new RequestPresentationPolicy();

        self::assertTrue($requestPresentationPolicy->isApiRequest(new ServerRequest([], [], '/api', 'GET')));
        self::assertTrue($requestPresentationPolicy->isApiRequest(new ServerRequest([], [], '/api/v1/users', 'GET')));
        self::assertFalse($requestPresentationPolicy->isApiRequest(new ServerRequest([], [], '/settings', 'GET')));
        self::assertFalse($requestPresentationPolicy->isApiRequest(new ServerRequest([], [], '/organization/api', 'GET')));
    }
}
