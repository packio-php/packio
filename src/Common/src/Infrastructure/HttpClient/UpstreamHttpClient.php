<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\Resource\ResourceLimits;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

use function array_values;
use function is_string;
use function min;
use function strlen;

final readonly class UpstreamHttpClient
{
    public function __construct(private ClientInterface $httpClient, private ResourceLimits $resourceLimits = new ResourceLimits()) {}

    /**
     * @param array<string, list<string>|string> $headers
     * @param null|array<string, mixed>          $json
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?array $json = null,
        ?string $body = null,
        float $timeout = 15.0,
    ): UpstreamHttpResponse {
        $options = [
            'connect_timeout' => 5.0,
            'headers'         => $headers,
            'http_errors'     => false,
            'allow_redirects' => false,
            'timeout'         => $timeout,
        ];

        if (null !== $json) {
            $options['json'] = $json;
        }

        if (null !== $body) {
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            throw UpstreamHttpException::fromGuzzleException($exception);
        }

        return new UpstreamHttpResponse(
            $response->getStatusCode(),
            $this->normalizeHeaders($response->getHeaders()),
            $this->boundedBody($response->getBody()),
        );
    }

    /**
     * @param array<string, list<string>|string> $headers
     * @param null|array<string, mixed>          $json
     */
    public function requestStream(
        string $method,
        string $url,
        array $headers = [],
        ?array $json = null,
        ?string $body = null,
        float $timeout = 60.0,
    ): UpstreamHttpStreamResponse {
        $options = [
            'connect_timeout' => 5.0,
            'headers'         => $headers,
            'http_errors'     => false,
            'allow_redirects' => false,
            'stream'          => true,
            'timeout'         => $timeout,
        ];

        if (null !== $json) {
            $options['json'] = $json;
        }

        if (null !== $body) {
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            throw UpstreamHttpException::fromGuzzleException($exception);
        }

        return new UpstreamHttpStreamResponse(
            $response->getStatusCode(),
            $this->normalizeHeaders($response->getHeaders()),
            $response->getBody(),
        );
    }

    private function boundedBody(StreamInterface $stream): string
    {
        $body = '';

        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (true) {
                $chunk = $stream->read(min(8192, $this->resourceLimits->upstreamBytes - strlen($body) + 1));
                if ('' === $chunk) {
                    if ($stream->eof()) {
                        break;
                    }

                    throw new UpstreamHttpException('Upstream response could not be read.');
                }
                $body .= $chunk;
                if (strlen($body) > $this->resourceLimits->upstreamBytes) {
                    throw new UpstreamHttpException('Upstream response exceeds the size limit.');
                }
            }

            return $body;
        } finally {
            $stream->close();
        }
    }

    /**
     * @param array<array<string>> $headers
     *
     * @return array<string, list<string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $name => $values) {
            if (! is_string($name)) {
                continue;
            }

            $normalizedHeaders[$name] = array_values($values);
        }

        return $normalizedHeaders;
    }
}
