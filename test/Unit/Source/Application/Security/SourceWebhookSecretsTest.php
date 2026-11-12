<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Security;

use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceWebhookSecrets::class)]
final class SourceWebhookSecretsTest extends TestCase
{
    #[Test]
    public function itEncryptsPlaintextWebhookSecretAndKeepsOtherKeys(): void
    {
        $sodiumSecretEncryptor  = new SodiumSecretEncryptor('key-a');
        $sourceWebhookSecrets   = new SourceWebhookSecrets($sodiumSecretEncryptor);

        $config = $sourceWebhookSecrets->encryptConfiguration([
            'webhook_secret' => 'plain',
            'base_url'       => 'https://x.test',
        ]);

        self::assertTrue($sodiumSecretEncryptor->isEncrypted((string) $config['webhook_secret']));
        self::assertSame('https://x.test', $config['base_url']);
        self::assertSame('plain', $sourceWebhookSecrets->decrypt((string) $config['webhook_secret']));
    }

    #[Test]
    public function itDoesNotEncryptTwiceOrTouchEmptySecrets(): void
    {
        $sourceWebhookSecrets   = new SourceWebhookSecrets(new SodiumSecretEncryptor('key-a'));
        $encrypted              = $sourceWebhookSecrets->encryptConfiguration([
            'webhook_secret' => 'plain',
        ]);

        self::assertSame($encrypted, $sourceWebhookSecrets->encryptConfiguration($encrypted));
        self::assertSame([
            'webhook_secret' => '',
        ], $sourceWebhookSecrets->encryptConfiguration([
            'webhook_secret' => '',
        ]));
        self::assertSame([
            'base_url' => 'x',
        ], $sourceWebhookSecrets->encryptConfiguration([
            'base_url' => 'x',
        ]));
    }

    #[Test]
    public function itReturnsLegacyPlaintextAsIsAndEmptyForUndecryptableValues(): void
    {
        $sourceWebhookSecrets      = new SourceWebhookSecrets(new SodiumSecretEncryptor('key-b'));
        $foreignValue              = new SodiumSecretEncryptor('key-a')->encrypt('plain');

        self::assertSame('legacy', $sourceWebhookSecrets->decrypt('legacy'));
        self::assertSame('', $sourceWebhookSecrets->decrypt($foreignValue));
        self::assertSame('', $sourceWebhookSecrets->decrypt(null));
        self::assertSame('', $sourceWebhookSecrets->decrypt(''));
    }
}
