<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Security;

use Common\Security\SodiumSecretEncryptor;
use Common\Security\StoredSecretResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function base64_encode;
use function chr;
use function ord;
use function strlen;
use function substr;

final class StoredSecretResolverTest extends TestCase
{
    public function testDecryptOnlyAcceptsEncryptedValuesWhileResolvePreservesLegacyPlaintext(): void
    {
        $storedSecretResolver = new StoredSecretResolver(new SodiumSecretEncryptor('test-key'));

        self::assertNull($storedSecretResolver->decrypt('legacy-token'));
        self::assertSame('legacy-token', $storedSecretResolver->resolve('legacy-token'));
    }

    public function testNullAndBlankValuesHaveEmptyResolverResults(): void
    {
        $storedSecretResolver = new StoredSecretResolver(new SodiumSecretEncryptor('test-key'));

        foreach ([null, '', " \t\n"] as $value) {
            self::assertNull($storedSecretResolver->decrypt($value));
            self::assertSame('', $storedSecretResolver->resolve($value));
        }
    }

    public function testEncryptedEmptyPlaintextRemainsAnEmptyString(): void
    {
        $storedSecretResolver    = new StoredSecretResolver(new SodiumSecretEncryptor('test-key'));
        $storedValue             = $storedSecretResolver->encrypt('');

        self::assertSame('', $storedSecretResolver->decrypt($storedValue));
        self::assertSame('', $storedSecretResolver->resolve($storedValue));
    }

    public function testEncryptedOrdinaryPlaintextRoundTripsExactly(): void
    {
        $storedSecretResolver  = new StoredSecretResolver(new SodiumSecretEncryptor('test-key'));
        $plaintext             = "provider-token\0with-bytes";

        self::assertSame($plaintext, $storedSecretResolver->decrypt($storedSecretResolver->encrypt($plaintext)));
    }

    #[DataProvider('failedEncryptedValues')]
    public function testFailedEncryptedDecryptionNeverFallsBackToCiphertext(string $envelope): void
    {
        $storedSecretResolver = new StoredSecretResolver(new SodiumSecretEncryptor('test-key'));

        self::assertNull($storedSecretResolver->decrypt($envelope));
        self::assertSame('', $storedSecretResolver->resolve($envelope));
        self::assertNotSame($envelope, $storedSecretResolver->resolve($envelope));
    }

    /** @return iterable<string, array{string}> */
    public static function failedEncryptedValues(): iterable
    {
        yield 'invalid base64' => ['secretbox:v1:!'];

        yield 'short nonce' => ['secretbox:v1:AA=='];

        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');

        yield 'tampered payload' => [self::tamperedEnvelope($sodiumSecretEncryptor->encrypt('provider-token'))];

        yield 'wrong key' => [new SodiumSecretEncryptor('other-key')->encrypt('provider-token')];
    }

    private static function tamperedEnvelope(string $envelope): string
    {
        $prefix  = 'secretbox:v1:';
        $decoded = base64_decode(substr($envelope, strlen($prefix)), true);
        self::assertIsString($decoded);
        $decoded[strlen($decoded) - 1] = chr(ord($decoded[strlen($decoded) - 1]) ^ 1);

        return $prefix . base64_encode($decoded);
    }
}
