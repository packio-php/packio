<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Exception\SourceOAuthAuthorizationException;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipStatus;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationOAuthStoreHandler;
use Packio\Source\Infrastructure\Request\ProviderAuthorizationOAuthRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function json_decode;

#[CoversClass(SourceAuthorizationOAuthStoreHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceAuthorizationHandlerTest extends TestCase
{
    /** @var (MockObject&SourceApiFacadeInterface) */
    private MockObject $sourceApi;

    protected function setUp(): void
    {
        $this->sourceApi = $this->createMock(SourceApiFacadeInterface::class);
    }

    // ── SourceAuthorizationOAuthStoreHandler ────────────────────────────────

    #[Test]
    public function oauthStoreReturns404WhenOrganizationMissing(): void
    {
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', sourceOauthAccountId: 'soa-1');
        $serverRequest                     = new ServerRequest()
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Organization not found', $body['error']);
        self::assertSame('error', $body['toast']['type']);
        self::assertSame('Organization not found', $body['toast']['message']);
    }

    #[Test]
    public function oauthStoreReturns422WhenBothIdsEmpty(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', oauthAccountId: '', sourceOauthAccountId: '');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Source OAuth account ID is required.', $body['error']);
        self::assertSame('error', $body['toast']['type']);
        self::assertSame('Source OAuth account ID is required.', $body['toast']['message']);
    }

    #[Test]
    public function oauthStoreRejectsIdentityOAuthAccountId(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', oauthAccountId: 'oa-1');
        $userDto                           = new UserDto('user-1', 'u@x.com', 'User', ['user'], '2025-01-01T00:00:00+00:00');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $this->sourceApi->expects(self::never())->method('authorizeProviderFromSourceOAuth');

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'Source authorization requires a source OAuth connection. Please reconnect the provider.',
            $body['error'],
        );
        self::assertSame('error', $body['toast']['type']);
        self::assertSame(
            'Source authorization requires a source OAuth connection. Please reconnect the provider.',
            $body['toast']['message'],
        );
    }

    #[Test]
    public function oauthStoreReturns401WhenUserNotAuthenticated(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', sourceOauthAccountId: 'soa-1');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Authentication required.', $body['error']);
        self::assertSame('error', $body['toast']['type']);
        self::assertSame('Authentication required.', $body['toast']['message']);
    }

    #[Test]
    public function oauthStoreAuthorizesFromSourceOAuthAccount(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', oauthAccountId: '', sourceOauthAccountId: 'soa-1');
        $userDto                           = new UserDto('user-1', 'u@x.com', 'User', ['user'], '2025-01-01T00:00:00+00:00');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $providerAuthorizationDto = new ProviderAuthorizationDto(
            id: 'auth-2',
            organizationId: 'org-1',
            provider: 'github',
            authStrategy: 'oauth',
            status: 'active',
            accountName: 'ghuser',
            configured: true,
            maskedValue: 'gh:****',
            sourceOAuthAccountId: 'soa-1',
        );

        $this->sourceApi->expects(self::once())
            ->method('authorizeProviderFromSourceOAuth')
            ->with(
                $actorContext,
                'user-1',
                'github',
                'soa-1',
            )
            ->willReturn($providerAuthorizationDto)
        ;

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('success', $body['toast']['type']);
        self::assertSame('Provider authorization created. You can now select repositories.', $body['toast']['message']);
        self::assertSame('soa-1', $body['authorization']['source_oauth_account_id']);
    }

    #[Test]
    public function oauthStoreReturns403WhenSourceOAuthAccountIsUnavailable(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', oauthAccountId: '', sourceOauthAccountId: 'soa-1');
        $userDto                           = new UserDto('user-1', 'u@x.com', 'User', ['user'], '2025-01-01T00:00:00+00:00');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $this->sourceApi->expects(self::once())
            ->method('authorizeProviderFromSourceOAuth')
            ->with($actorContext, 'user-1', 'github', 'soa-1')
            ->willThrowException(new SourceOAuthAuthorizationException(
                SourceOAuthAccountOwnershipStatus::Unavailable,
                'Source OAuth account is not available.',
            ))
        ;

        $response = $this->oauthStoreHandler()->handle($serverRequest);

        self::assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Provider authorization failed.', $body['error']);
        self::assertSame('error', $body['toast']['type']);
        self::assertSame('Provider authorization failed.', $body['toast']['message']);
        self::assertStringNotContainsString('Source OAuth account is not available.', $body['toast']['message']);
    }

    #[Test]
    public function oauthStoreReturns401WhenSourceOAuthTokenIsUnavailable(): void
    {
        $organizationDto                   = new OrganizationDto('org-1', 'my-org', 'My Org');
        $actorContext                      = new ActorContext('org-1', 'user-1', 'owner');
        $providerAuthorizationOAuthRequest = new ProviderAuthorizationOAuthRequest(provider: 'github', sourceOauthAccountId: 'soa-1');
        $userDto                           = new UserDto('user-1', 'u@x.com', 'User', ['user'], '2025-01-01T00:00:00+00:00');

        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto)
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, $actorContext)
            ->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto)
            ->withAttribute(ProviderAuthorizationOAuthRequest::class, $providerAuthorizationOAuthRequest)
        ;

        $this->sourceApi->expects(self::once())
            ->method('authorizeProviderFromSourceOAuth')
            ->with($actorContext, 'user-1', 'github', 'soa-1')
            ->willThrowException(new SourceOAuthAuthorizationException(
                SourceOAuthAccountOwnershipStatus::TokenUnavailable,
                'Token scope insufficient',
            ))
        ;

        $sourceAuthorizationOAuthStoreHandler = $this->oauthStoreHandler();
        $response                             = $sourceAuthorizationOAuthStoreHandler->handle($serverRequest);

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Provider authorization failed.', $body['error']);
        self::assertSame('error', $body['toast']['type']);
        self::assertSame('Provider authorization failed.', $body['toast']['message']);
        self::assertStringNotContainsString('Token scope insufficient', $body['toast']['message']);
    }

    private function oauthStoreHandler(): SourceAuthorizationOAuthStoreHandler
    {
        return new SourceAuthorizationOAuthStoreHandler(
            $this->sourceApi,
        );
    }
}
