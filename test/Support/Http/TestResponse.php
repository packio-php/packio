<?php

declare(strict_types=1);

namespace PackioTest\Support\Http;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function explode;
use function is_array;
use function json_decode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strcasecmp;
use function substr;
use function trim;

final readonly class TestResponse
{
    public function __construct(private ResponseInterface $response) {}

    public function assertStatus(int $status): self
    {
        Assert::assertSame(
            $status,
            $this->response->getStatusCode(),
            'Unexpected response status. Body: ' . substr($this->body(), 0, 1000),
        );

        return $this;
    }

    public function assertNotStatus(int $status): self
    {
        Assert::assertNotSame(
            $status,
            $this->response->getStatusCode(),
            'Unexpected response status',
        );

        return $this;
    }

    public function assertOk(): self
    {
        $status = $this->response->getStatusCode();
        Assert::assertGreaterThanOrEqual(200, $status);
        Assert::assertLessThan(300, $status);

        return $this;
    }

    public function assertRedirect(?string $location = null): self
    {
        $status = $this->response->getStatusCode();
        Assert::assertGreaterThanOrEqual(300, $status);
        Assert::assertLessThan(400, $status);

        if (null === $location) {
            return $this;
        }

        Assert::assertStringContainsString($location, $this->header('Location'));

        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        Assert::assertTrue($this->response->hasHeader($name), sprintf('Header "%s" is missing', $name));

        if (null !== $value) {
            Assert::assertSame($value, $this->response->getHeaderLine($name));
        }

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        $payload = $this->json();
        $actual  = $this->extractJsonPath($payload, $path);

        Assert::assertSame($expected, $actual, sprintf('JSON path "%s" mismatch', $path));

        return $this;
    }

    public function assertInertiaComponent(string $component): self
    {
        $response = $this->json();
        Assert::assertArrayHasKey('component', $response);
        Assert::assertSame($component, $response['component']);

        return $this;
    }

    public function assertInertiaResponse(): self
    {
        $contentType = $this->header('Content-Type');
        Assert::assertTrue(
            str_starts_with($contentType, 'application/json'),
            sprintf('Expected an Inertia JSON response, received Content-Type "%s"', $contentType),
        );
        $this->assertHeader('X-Inertia', 'true');

        $varyTokens = array_values(array_filter(array_map(
            trim(...),
            explode(',', $this->header('Vary')),
        )));
        $inertiaTokens = array_filter(
            $varyTokens,
            static fn (string $token): bool => 0 === strcasecmp($token, 'X-Inertia'),
        );
        Assert::assertCount(1, $inertiaTokens, 'Vary must contain X-Inertia exactly once');

        return $this;
    }

    public function assertInitialInertiaPage(): self
    {
        $contentType = $this->header('Content-Type');
        Assert::assertTrue(
            str_starts_with($contentType, 'text/html'),
            sprintf('Expected an Inertia HTML response, received Content-Type "%s"', $contentType),
        );

        $matches = [];
        Assert::assertSame(
            1,
            preg_match(
                '~<script\s+data-page="app"\s+type="application/json">(?<page>.*?)</script>~s',
                $this->body(),
                $matches,
            ),
            'Initial response is missing its Inertia page script',
        );
        Assert::assertSame(1, preg_match('~<div\s+id="app"></div>~', $this->body()), 'Initial response is missing #app');
        Assert::assertSame(0, preg_match('~<div\b[^>]*\bdata-page(?:\s|=|>)~i', $this->body()), 'Initial response must not use a data-page div');

        if (! isset($matches['page'])) {
            Assert::fail('Initial response is missing its Inertia page script');
        }

        $page = json_decode($matches['page'], true);
        Assert::assertIsArray($page, 'Initial Inertia page script must contain valid JSON');
        Assert::assertArrayHasKey('component', $page);
        Assert::assertArrayHasKey('props', $page);
        Assert::assertArrayHasKey('url', $page);
        Assert::assertArrayHasKey('version', $page);

        return $this;
    }

    public function assertInertiaVersion(string $version): self
    {
        return $this->assertJsonPath('version', $version);
    }

    public function assertInertiaLocation(string $location): self
    {
        return $this->assertHeader('X-Inertia-Location', $location);
    }

    public function assertNoInertiaResponse(): self
    {
        Assert::assertFalse($this->response->hasHeader('X-Inertia'), 'Response must not have an X-Inertia header');

        return $this;
    }

    public function assertInertiaProp(string $path, mixed $expected): self
    {
        return $this->assertJsonPath('props.' . $path, $expected);
    }

    /** @return array<string, mixed> */
    public function inertiaPage(): array
    {
        $response = $this->json();

        if (isset($response['component'])) {
            return $response;
        }

        return $response['props'] ?? [];
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body(), true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function header(string $name): string
    {
        if (! $this->response->hasHeader($name)) {
            return '';
        }

        return $this->response->getHeaderLine($name);
    }

    public function response(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractJsonPath(array $payload, string $path): mixed
    {
        $parts   = explode('.', $path);
        $current = $payload;

        foreach ($parts as $part) {
            if (! is_array($current)) {
                Assert::fail(sprintf('Expected an array while resolving "%s"', $path));
            }

            if (! array_key_exists($part, $current)) {
                Assert::fail(sprintf('JSON path "%s" was not found', $path));
            }

            $current = $current[$part];
        }

        return $current;
    }
}
