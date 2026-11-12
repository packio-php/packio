<?php

declare(strict_types=1);

namespace Common\Infrastructure\HttpClient\Exception;

use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class UpstreamHttpException extends RuntimeException
{
    public static function fromGuzzleException(GuzzleException $guzzleException): self
    {
        return new self($guzzleException->getMessage(), (int) $guzzleException->getCode(), $guzzleException);
    }
}
