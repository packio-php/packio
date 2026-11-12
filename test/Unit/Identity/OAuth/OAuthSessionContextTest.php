<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Security\SecretEncryptorInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Fake\InMemorySession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthSessionContext::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthSessionContextTest extends TestCase
{
    #[Test]
    public function pendingEmailContextStoresOauthTokensEncryptedInSession(): void
    {
        $secretEncryptor = $this->createMock(SecretEncryptorInterface::class);
        $secretEncryptor->expects(self::exactly(2))
            ->method('encrypt')
            ->willReturnMap([
                ['access-token', 'enc-access-token'],
                ['refresh-token', 'enc-refresh-token'],
            ])
        ;
        $secretEncryptor->expects(self::exactly(2))
            ->method('decrypt')
            ->willReturnMap([
                ['enc-access-token', 'access-token'],
                ['enc-refresh-token', 'refresh-token'],
            ])
        ;

        $inMemorySession     = new InMemorySession();
        $oAuthSessionContext = new OAuthSessionContext($secretEncryptor);

        $oAuthSessionContext->storePendingEmailContext(
            $inMemorySession,
            PendingOAuthEmailContext::fromOAuth(
                OAuthProvider::Codeberg,
                new OAuthTokenResponse('access-token', 'refresh-token', 3600),
                new OAuthUserData('codeberg-user-1', null, 'cb-user', 'Codeberg User', null),
                '/target',
            ),
        );

        $stored = $inMemorySession->get('oauth_pending_email_context');
        self::assertIsArray($stored);
        self::assertSame('enc-access-token', $stored['access_token'] ?? null);
        self::assertSame('enc-refresh-token', $stored['refresh_token'] ?? null);

        $resolved = $oAuthSessionContext->pendingEmailContext($inMemorySession);
        self::assertInstanceOf(PendingOAuthEmailContext::class, $resolved);
        self::assertSame('access-token', $resolved->accessToken);
        self::assertSame('refresh-token', $resolved->refreshToken);
    }
}
