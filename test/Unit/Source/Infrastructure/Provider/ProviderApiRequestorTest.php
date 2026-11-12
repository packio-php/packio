<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Provider;

use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderError;
use Packio\Source\Api\Exception\ProviderFailure;
use Packio\Source\Infrastructure\Provider\ProviderApiRequestor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderApiRequestorTest extends TestCase
{
    #[DataProvider('failures')]
    public function testTypedHttpFailures(int $status, string $body, ProviderFailure $providerFailure, int $publicStatus): void
    {
        $providerApiRequestor = new ProviderApiRequestor(new UpstreamHttpClient(new Client([
            'handler' => HandlerStack::create(new MockHandler([new Response($status, [], $body)])),
        ])));

        try {
            $providerApiRequestor->getResourcesJson('https://provider.test', []);
            self::fail('Expected typed failure.');
        } catch (ProviderCallException $exception) {
            self::assertSame($providerFailure, $exception->failure);
            $error = ProviderError::fromException($exception);
            self::assertSame($publicStatus, $error->status);
            self::assertSame($providerFailure->value, $error->code);
            self::assertStringNotContainsString('secret', $error->message);
        }
    }

    /** @return iterable<string, array{int, string, ProviderFailure, int}> */
    public static function failures(): iterable
    {
        yield 'denied' => [403, '{"message":"token=secret"}', ProviderFailure::AccessDenied, 403];

        yield 'limited' => [429, 'token=secret', ProviderFailure::RateLimited, 429];

        yield 'unavailable' => [503, 'token=secret', ProviderFailure::Unavailable, 502];

        yield 'bad json' => [200, 'token=secret', ProviderFailure::InvalidResponse, 502];

        yield 'unexpected status' => [404, '{}', ProviderFailure::InvalidResponse, 502];
    }

    public function testNetworkFailurePreservesCause(): void
    {
        $connectException     = new ConnectException('Connection failed: token=secret', new Request('GET', 'https://provider.test'));
        $providerApiRequestor = new ProviderApiRequestor(new UpstreamHttpClient(new Client([
            'handler' => HandlerStack::create(new MockHandler([$connectException])),
        ])));

        try {
            $providerApiRequestor->getResourcesJson('https://provider.test', []);
            self::fail('Expected typed network failure.');
        } catch (ProviderCallException $exception) {
            self::assertSame(ProviderFailure::Unavailable, $exception->failure);
            self::assertSame($connectException, $exception->getPrevious()?->getPrevious());
            self::assertStringNotContainsString('secret', ProviderError::fromException($exception)->message);
        }
    }
}
