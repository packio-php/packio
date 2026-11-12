<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient;

use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

use function fopen;
use function strlen;

final class BoundedResponseStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    public function __construct(private readonly int $maxBytes)
    {
        $this->stream = Utils::streamFor(fopen('php://temp/maxmemory:1048576', 'w+b'));
    }

    public function write(string $string): int
    {
        if ($this->stream->tell() + strlen($string) > $this->maxBytes) {
            throw new UpstreamHttpException('Upstream response exceeds the size limit.');
        }

        return $this->stream->write($string);
    }
}
