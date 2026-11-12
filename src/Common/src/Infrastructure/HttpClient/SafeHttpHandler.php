<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Closure;
use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_keys;
use function array_map;
use function filter_var;
use function implode;
use function in_array;
use function str_contains;
use function strtolower;
use function trim;

/** Applies the policy at every connection, including every redirect. */
final readonly class SafeHttpHandler
{
    public function __construct(
        private OutboundUrlPolicy $outboundUrlPolicy,
        private Closure $transport,
        private ResourceLimits $resourceLimits = new ResourceLimits()
    ) {}

    /** @param array<string, mixed> $options */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        return $this->send($request, $options, 0);
    }

    /** @param array<string, mixed> $options */
    private function send(RequestInterface $request, array $options, int $redirects): PromiseInterface
    {
        $target                     = $this->outboundUrlPolicy->resolve((string) $request->getUri());
        $addresses                  = array_map(static fn (string $ip): string => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, $target['addresses']);
        $options['sink']            = new BoundedResponseStream(($options['stream'] ?? false) ? $this->resourceLimits->archiveBytes : $this->resourceLimits->upstreamBytes);
        $options['proxy']           = '';
        $options['verify']          = true;
        $options['allow_redirects'] = false;
        $options['protocols']       = ['https'];
        $options['curl']            = [
            CURLOPT_RESOLVE        => [$target['host'] . ':' . $target['port'] . ':' . implode(',', $addresses)],
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_FORBID_REUSE   => true,
        ];
        if (false !== filter_var(trim($target['host'], '[]'), FILTER_VALIDATE_IP)) {
            unset($options['curl'][CURLOPT_RESOLVE]);
        }
        // Host is derived exclusively from the validated URI.
        $request = $request->withHeader('Host', $request->getUri()->getHost() . (null === $request->getUri()->getPort() ? '' : ':' . $request->getUri()->getPort()));

        return ($this->transport)($request, $options)->then(function(ResponseInterface $response) use ($request, $options, $redirects, $target): PromiseInterface|ResponseInterface {
            if (! in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true) || ! $response->hasHeader('Location')) {
                return $response;
            }
            $response->getBody()->close();
            if ($redirects >= 5 || ! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
                throw new RuntimeException('Upstream redirect is not allowed.');
            }
            $uri  = UriResolver::resolve($request->getUri(), new Uri($response->getHeaderLine('Location')));
            $next = $this->outboundUrlPolicy->parse((string) $uri);
            if ($next['origin'] !== $target['origin']) {
                // Provider tokens can use arbitrary header names. Retain only benign negotiation headers.
                foreach (array_keys($request->getHeaders()) as $name) {
                    if (! in_array(strtolower($name), ['accept', 'accept-encoding', 'user-agent'], true)) {
                        $request = $request->withoutHeader($name);
                    }
                }
            }

            return $this->send($request->withUri($uri), $options, $redirects + 1);
        });
    }
}
