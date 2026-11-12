<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Application\Security;

use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ApiAccessTokenHasher::class)]
final class ApiAccessTokenHasherTest extends TestCase
{
    #[Test]
    public function producesDeterministicHashForSameTokenAndKey(): void
    {
        $apiAccessTokenHasher = new ApiAccessTokenHasher('secret-key-material');

        self::assertSame($apiAccessTokenHasher->hash('packio_api_token'), $apiAccessTokenHasher->hash('packio_api_token'));
    }

    #[Test]
    public function differentKeysProduceDifferentHashes(): void
    {
        $first  = new ApiAccessTokenHasher('first-secret-key-material');
        $second = new ApiAccessTokenHasher('second-secret-key-material');

        self::assertNotSame($first->hash('packio_api_token'), $second->hash('packio_api_token'));
    }

    #[Test]
    public function emptyProductionKeyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET_ENCRYPTION_KEY must be configured in production.');

        new ApiAccessTokenHasher('', productionMode: true);
    }

    #[Test]
    public function emptyNonProductionKeyUsesDevelopmentFallback(): void
    {
        $fallback = new ApiAccessTokenHasher('packio-dev-api-access-token');
        $empty    = new ApiAccessTokenHasher('');

        self::assertSame($fallback->hash('packio_api_token'), $empty->hash('packio_api_token'));
    }
}
