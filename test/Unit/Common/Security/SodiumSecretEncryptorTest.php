<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Security;

use Common\Security\SodiumSecretEncryptor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function base64_decode;
use function base64_encode;
use function chr;
use function ord;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

final class SodiumSecretEncryptorTest extends TestCase
{
    public function testEncryptsAndDecryptsEmptyPlaintext(): void
    {
        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');

        self::assertSame('', $sodiumSecretEncryptor->decrypt($sodiumSecretEncryptor->encrypt('')));
    }

    public function testEncryptsAndDecryptsOrdinaryPlaintextExactly(): void
    {
        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');
        $plaintext             = "provider-token\0with-bytes";

        self::assertSame($plaintext, $sodiumSecretEncryptor->decrypt($sodiumSecretEncryptor->encrypt($plaintext)));
    }

    #[DataProvider('malformedEnvelopes')]
    public function testReturnsNullForMalformedEncryptedEnvelope(string $envelope): void
    {
        self::assertNull(new SodiumSecretEncryptor('test-key')->decrypt($envelope));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedEnvelopes(): iterable
    {
        yield 'invalid base64' => ['secretbox:v1:!'];

        foreach ([0, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 1] as $nonceBytes) {
            yield sprintf('nonce length %d', $nonceBytes) => [self::envelope(str_repeat("\0", $nonceBytes))];
        }

        foreach ([0, 1, SODIUM_CRYPTO_SECRETBOX_MACBYTES - 1] as $payloadBytes) {
            yield sprintf('payload length %d', $payloadBytes) => [
                self::envelope(str_repeat("\0", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) . str_repeat("\0", $payloadBytes)),
            ];
        }
    }

    public function testReturnsNullForTamperedCiphertext(): void
    {
        $sodiumSecretEncryptor = new SodiumSecretEncryptor('test-key');
        $envelope              = $sodiumSecretEncryptor->encrypt('provider-token');
        $encoded               = substr($envelope, strlen('secretbox:v1:'));
        $decoded               = base64_decode($encoded, true);
        self::assertIsString($decoded);
        $decoded[strlen($decoded) - 1] = chr(ord($decoded[strlen($decoded) - 1]) ^ 1);

        self::assertNull($sodiumSecretEncryptor->decrypt('secretbox:v1:' . base64_encode($decoded)));
    }

    public function testReturnsNullWhenCiphertextUsesAnotherKey(): void
    {
        $storedValue = new SodiumSecretEncryptor('other-key')->encrypt('provider-token');

        self::assertNull(new SodiumSecretEncryptor('test-key')->decrypt($storedValue));
    }

    public function testLegacyPlaintextRemainsAvailableFromLowLevelDecrypt(): void
    {
        self::assertSame('legacy-token', new SodiumSecretEncryptor('test-key')->decrypt('legacy-token'));
    }

    public function testProductionConfigurationFailureRemainsAConstructorException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET_ENCRYPTION_KEY must be configured in production.');

        new SodiumSecretEncryptor('', productionMode: true);
    }

    public function testInvalidConfiguredKeyRemainsAConstructorException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret encryption key is invalid.');

        new SodiumSecretEncryptor('base64:!');
    }

    private static function envelope(string $decoded): string
    {
        return 'secretbox:v1:' . base64_encode($decoded);
    }
}
