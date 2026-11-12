<?php

declare(strict_types=1);

namespace PackioTest\Support\Base;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Packio\Application\Release\AppReleaseVersion;
use PackioTest\Support\Http\TestResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function array_keys;
use function array_map;
use function dirname;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function ltrim;
use function parse_str;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtoupper;

abstract class FeatureTestCase extends IntegrationTestCase
{
    private ?Application $application = null;
    private string $host              = 'localhost';
    private bool $ensureCsrf          = false;

    /** @var array<string, string> */
    private array $cookies    = [];
    private string $csrfToken = 'test-csrf-token';

    public function withCsrf(string $pageUri = '/auth/login'): static
    {
        if ('test-csrf-token' === $this->csrfToken) {
            $this->loadCsrfToken($pageUri);
        }

        $this->ensureCsrf = true;

        return $this;
    }

    public function withHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param array<string, mixed> $headers
     */
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     */
    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    protected function postJson(string $uri, array $payload = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $payload, $headers, true);
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, mixed>        $headers
     */
    protected function request(
        string $method,
        string $uri,
        array|string $body = [],
        array $headers = [],
        bool $isJson = false,
    ): TestResponse {
        $application   = $this->application();
        $headers       = $this->ensureInertiaVersionHeader($headers);
        $serverRequest = $this->buildRequest($method, $uri, $body, $headers, $isJson);

        $response     = $application->handle($serverRequest);
        $testResponse = new TestResponse($response);
        $this->storeResponseCookies($response);

        if ($this->ensureCsrf
            && 'POST' === strtoupper($method)
            && '/auth/login' === $uri
            && '/auth/login' !== $testResponse->header('Location')
            && $testResponse->status() >= 300
            && $testResponse->status() < 400
        ) {
            // A successful authentication transition rotates the server token. The
            // browser obtains the replacement only when it follows the redirect.
            $location = $testResponse->header('Location');
            if (str_starts_with($location, '/')) {
                $this->loadCsrfToken($location);
            }
        }

        return $testResponse;
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, mixed>        $headers
     */
    protected function buildRequest(
        string $method,
        string $uri,
        array|string $body,
        array $headers,
        bool $isJson,
    ): ServerRequestInterface {
        $uri = ltrim($uri, '/');
        $url = sprintf('https://%s/%s', $this->host, $uri);

        $request = new ServerRequest(
            uri: $url,
            method: strtoupper($method),
        )->withHeader('Host', $this->host);
        $queryString = $request->getUri()->getQuery();
        if ('' !== $queryString) {
            $queryParams = [];
            parse_str($queryString, $queryParams);
            $request = $request->withQueryParams($queryParams);
        }

        if ([] !== $this->cookies) {
            $request = $request
                ->withHeader('Cookie', $this->serializeCookies())
                ->withCookieParams($this->cookies)
            ;
        }

        if ($this->ensureCsrf && ! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $request = $request->withHeader('X-CSRF-Token', $this->csrfToken);
            $request = $request->withHeader('Origin', 'https://' . $this->host);
        }

        foreach ($headers as $name => $value) {
            $request = is_array($value) ? $request->withHeader($name, $value) : $request->withHeader($name, (string) $value);
        }

        if ('GET' === strtoupper($method)) {
            return $request;
        }

        if ($isJson) {
            $stream = new StreamFactory()->createStream(
                is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body,
            );

            return $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream)
            ;
        }

        if (is_string($body)) {
            return $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(new StreamFactory()->createStream($body))
            ;
        }

        return $request->withParsedBody($body)->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    protected function clearCookies(): void
    {
        $this->cookies = [];
    }

    protected function loadCsrfToken(string $uri = '/auth/login'): string
    {
        $previousEnsureCsrf = $this->ensureCsrf;
        $this->ensureCsrf   = false;

        $testResponse = $this->get($uri, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $this->ensureCsrf = $previousEnsureCsrf;

        $token = $testResponse->json()['props']['csrf']['token'] ?? null;
        self::assertIsString($token, 'Expected an Inertia csrf.token shared prop.');
        self::assertNotSame('', $token, 'Expected a non-empty Inertia csrf.token shared prop.');

        $this->csrfToken = $token;

        return $this->csrfToken;
    }

    protected function rebuildApplication(): void
    {
        $this->rebuildContainer();
        $this->application = null;
    }

    protected function application(): Application
    {
        if ($this->application instanceof Application) {
            return $this->application;
        }

        $application = $this->container->get(Application::class);

        /** @var MiddlewareFactory $factory */
        $factory = $this->container->get(MiddlewareFactory::class);

        (require dirname(__DIR__, 2) . '/../config/pipeline.php')($application, $factory, $this->container);
        (require dirname(__DIR__, 2) . '/../config/routes.php')($application, $factory, $this->container);

        $this->application = $application;

        return $application;
    }

    private function serializeCookies(): string
    {
        if ([] === $this->cookies) {
            return '';
        }

        return implode('; ', array_map(
            static fn (string $name, string $value): string => $name . '=' . $value,
            array_keys($this->cookies),
            $this->cookies,
        ));
    }

    private function storeResponseCookies(ResponseInterface $response): void
    {
        $setCookieHeaders = $response->getHeader('Set-Cookie');

        foreach ($setCookieHeaders as $setCookieHeader) {
            $pair = explode(';', $setCookieHeader, 2)[0];
            if ('' === $pair) {
                continue;
            }

            if (false === str_contains($pair, '=')) {
                continue;
            }

            $parts = explode('=', $pair, 2);
            if ('' === $parts[0]) {
                continue;
            }

            $this->cookies[$parts[0]] = $parts[1];
        }
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return array<string, mixed>
     */
    private function ensureInertiaVersionHeader(array $headers): array
    {
        if (! array_key_exists('X-Inertia', $headers) || array_key_exists('X-Inertia-Version', $headers)) {
            return $headers;
        }

        if (! isset($this->container) || ! $this->container->has(AppReleaseVersion::class)) {
            return $headers;
        }

        $release = $this->container->get(AppReleaseVersion::class);
        if (! $release instanceof AppReleaseVersion) {
            return $headers;
        }

        $version = $release->toArray()['sha'];
        if ('' === $version) {
            return $headers;
        }

        $headers['X-Inertia-Version'] = $version;

        return $headers;
    }
}
