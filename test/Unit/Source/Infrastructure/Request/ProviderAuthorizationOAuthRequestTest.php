<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Request;

use Packio\Source\Infrastructure\Request\ProviderAuthorizationOAuthRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;

#[CoversClass(ProviderAuthorizationOAuthRequest::class)]
final class ProviderAuthorizationOAuthRequestTest extends TestCase
{
    #[Test]
    public function keepsOAuthAccountId(): void
    {
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(
            provider: 'github',
            oauthAccountId: 'oa-1',
        );

        self::assertSame('oa-1', $providerAuthorizationOAuthRequest->oauthAccountId);
        self::assertSame('', $providerAuthorizationOAuthRequest->sourceOauthAccountId);
    }

    #[Test]
    public function keepsSourceOauthAccountId(): void
    {
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(
            provider: 'github',
            sourceOauthAccountId: 'soa-1',
        );

        self::assertSame('', $providerAuthorizationOAuthRequest->oauthAccountId);
        self::assertSame('soa-1', $providerAuthorizationOAuthRequest->sourceOauthAccountId);
    }

    #[Test]
    public function acceptsBitbucketProvider(): void
    {
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(
            provider: 'bitbucket',
            sourceOauthAccountId: '0196f38d-3c8c-73d0-b184-e624de30bdf2',
        );

        $result = new Validator()->validate($providerAuthorizationOAuthRequest);

        self::assertTrue($result->isValid());
    }
}
