<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\HostResolver;
use Common\Infrastructure\HttpClient\OutboundUrlPolicy;
use Common\Infrastructure\HttpClient\SafeHttpHandler;
use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function count;

final class SafeHttpHandlerTest extends TestCase
{
    public function testNativeCurlConfigurationIsCompatibleWithoutOpeningAConnection(): void
    {
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static function(RequestInterface $request, array $options): PromiseInterface {
            $curlFactory = new CurlFactory(0);
            $easyHandle  = $curlFactory->create($request, $options);
            $curlFactory->release($easyHandle);

            return Create::promiseFor(new Response(204));
        });
        self::assertSame(204, $safeHttpHandler(new Request('GET', 'https://example.test'), [])->wait()->getStatusCode());
    }

    public function testTransportSinkStopsOversizedDecodedResponse(): void
    {
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static function(RequestInterface $request, array $options): PromiseInterface {
            $options['sink']->write('12345678');
            $options['sink']->write('9');

            return Create::promiseFor(new Response());
        }, new ResourceLimits(upstreamBytes: 8));
        $this->expectException(UpstreamHttpException::class);
        $safeHttpHandler(new Request('GET', 'https://example.test'), [])->wait();
    }

    public function testArchiveTransportUsesItsOwnSizeLimit(): void
    {
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static function(RequestInterface $request, array $options): PromiseInterface {
            $options['sink']->write('123456789');

            return Create::promiseFor(new Response(200, [], $options['sink']));
        }, new ResourceLimits(upstreamBytes: 8, archiveBytes: 16));
        self::assertSame('123456789', (string) $safeHttpHandler(new Request('GET', 'https://example.test'), [
            'stream' => true,
        ])->wait()->getBody());
    }

    public function testPinsConnectionAndStripsAllCredentialsOnCrossOriginRedirect(): void
    {
        $calls           = [];
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static function(RequestInterface $request, array $options) use (&$calls): PromiseInterface {
            $calls[] = [$request, $options];

            return Create::promiseFor(1 === count($calls) ? new Response(302, [
                'Location' => 'https://cdn.example.test/archive',
            ]) : new Response(200, [], 'zip'));
        });
        $response = $safeHttpHandler(new Request('GET', 'https://api.example.test/archive', [
            'Authorization' => 'secret',
            'PRIVATE-TOKEN' => 'secret2',
            'Cookie'        => 'session=secret',
            'Accept'        => 'application/zip',
        ]), [
            'proxy'  => 'http://unsafe.proxy',
            'verify' => false,
        ])->wait();
        self::assertSame('zip', (string) $response->getBody());
        self::assertCount(2, $calls);
        self::assertSame(['api.example.test:443:93.184.216.34'], $calls[0][1]['curl'][CURLOPT_RESOLVE]);
        self::assertSame(['cdn.example.test:443:93.184.216.34'], $calls[1][1]['curl'][CURLOPT_RESOLVE]);
        self::assertFalse($calls[0][1]['allow_redirects']);
        self::assertSame(['https'], $calls[0][1]['protocols']);
        self::assertSame('', $calls[0][1]['proxy']);
        self::assertTrue($calls[0][1]['verify']);
        self::assertSame('', $calls[1][0]->getHeaderLine('Authorization'));
        self::assertSame('', $calls[1][0]->getHeaderLine('PRIVATE-TOKEN'));
        self::assertSame('', $calls[1][0]->getHeaderLine('Cookie'));
        self::assertSame('application/zip', $calls[1][0]->getHeaderLine('Accept'));
    }

    public function testPrivateRedirectNeverReachesTransport(): void
    {
        $calls           = 0;
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static function() use (&$calls): PromiseInterface {
            ++$calls;

            return Create::promiseFor(new Response(302, [
                'Location' => 'https://169.254.169.254/latest',
            ]));
        });

        try {
            $safeHttpHandler(new Request('GET', 'https://example.test'), [])->wait();
            self::fail('Private redirect was accepted.');
        } catch (InvalidArgumentException) {
            self::assertSame(1, $calls);
        }
    }

    public function testRechecksDnsOnSameOriginRedirect(): void
    {
        $resolver = $this->createMock(HostResolver::class);
        $resolver->expects(self::exactly(2))->method('resolve')->willReturnOnConsecutiveCalls(['93.184.216.34'], ['127.0.0.1']);
        $calls           = 0;
        $safeHttpHandler = new SafeHttpHandler(new OutboundUrlPolicy(hostResolver: $resolver), static function() use (&$calls): PromiseInterface {
            ++$calls;

            return Create::promiseFor(new Response(302, [
                'Location' => '/second',
            ]));
        });

        try {
            $safeHttpHandler(new Request('GET', 'https://example.test/first'), [])->wait();
            self::fail('DNS rebinding was accepted.');
        } catch (InvalidArgumentException) {
            self::assertSame(1, $calls);
        }
    }

    public function testRedirectLoopIsBounded(): void
    {
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static fn (): PromiseInterface => Create::promiseFor(new Response(302, [
            'Location' => '/loop',
        ])));
        $this->expectException(RuntimeException::class);
        $safeHttpHandler(new Request('GET', 'https://example.test/loop'), [])->wait();
    }

    public function testDoesNotReplayPostBodyOnRedirect(): void
    {
        $safeHttpHandler = new SafeHttpHandler(OutboundPolicyFixture::policy(), static fn (): PromiseInterface => Create::promiseFor(new Response(307, [
            'Location' => 'https://other.example.test',
        ])));
        $this->expectException(RuntimeException::class);
        $safeHttpHandler(new Request('POST', 'https://example.test', [], 'client_secret=secret'), [])->wait();
    }
}
