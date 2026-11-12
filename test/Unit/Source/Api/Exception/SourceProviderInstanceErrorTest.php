<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Api\Exception;

use Error;
use Packio\Source\Api\Exception\SourceProviderInstanceError;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SourceProviderInstanceErrorTest extends TestCase
{
    #[DataProvider('errors')]
    public function testMapping(Throwable $throwable, int $status, string $code): void
    {
        $sourceProviderInstanceError = SourceProviderInstanceError::fromException($throwable);
        self::assertSame($status, $sourceProviderInstanceError->status);
        self::assertSame($code, $sourceProviderInstanceError->code);
        self::assertStringNotContainsString('secret', $sourceProviderInstanceError->message);
    }

    /** @return iterable<string, array{Throwable, int, string}> */
    public static function errors(): iterable
    {
        yield 'missing' => [SourceProviderInstanceException::notFound(), 404, 'source_provider_instance_not_found'];

        yield 'in use' => [SourceProviderInstanceException::inUse(), 409, 'source_provider_instance_in_use'];

        yield 'duplicate' => [SourceProviderInstanceException::duplicate(), 409, 'source_provider_instance_in_use'];

        yield 'invalid URL' => [SourceProviderInstanceException::invalidBaseUrl(), 422, 'source_provider_instance_invalid'];

        yield 'invalid provider containing not found' => [SourceProviderInstanceException::unsupportedProviderType('secret not found'), 422, 'source_provider_instance_invalid'];

        yield 'invalid auth' => [SourceProviderInstanceException::unsupportedAuthMode('secret'), 422, 'source_provider_instance_invalid'];

        yield 'credentials required' => [SourceProviderInstanceException::oauthClientCredentialsRequired(), 422, 'source_provider_instance_invalid'];

        yield 'ownership' => [SourceProviderInstanceException::forbidden(), 403, 'forbidden'];

        yield 'unexpected runtime' => [new RuntimeException('secret not found'), 500, 'internal_error'];

        yield 'unexpected error' => [new Error('secret'), 500, 'internal_error'];
    }
}
