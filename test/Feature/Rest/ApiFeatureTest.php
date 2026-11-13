<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Application\Settings\RuntimeSettingOverride;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\AdminOrganizationUpdateHandler;
use Packio\Rest\Infrastructure\Handler\AdminSettingsUpdateHandler;
use Packio\Rest\Infrastructure\Handler\AdminUserUpdateHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationMemberUpdateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationUpdateHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\ReleaseActivityHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstancesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Rest\Infrastructure\Handler\SourcesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceUpdateHandler;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Handler\SourceSettingsUpdateHandler;
use Packio\Webhook\Domain\Entity\WebhookEvent;
use Packio\Webhook\Domain\Repository\WebhookEventRepositoryInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Fake\FakeRateLimiter;
use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

use function array_any;
use function array_column;
use function array_filter;
use function array_key_last;
use function array_keys;
use function bin2hex;
use function count;
use function random_bytes;
use function str_starts_with;

final class ApiFeatureTest extends FeatureTestCase
{
    private FakeRateLimiter $fakeRateLimiter;

    protected function setUp(): void
    {
        $this->fakeRateLimiter                                   = new FakeRateLimiter();
        $this->serviceOverrides[RateLimiterInterface::class]     = $this->fakeRateLimiter;

        parent::setUp();
    }

    #[Test]
    public function itExposesPublicHealthAndDocumentationEndpoints(): void
    {
        $this->get('/api/v1/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'OK')
        ;

        $testResponse = $this->get('/api/doc.json');
        $testResponse->assertStatus(200);
        $openApi = $testResponse->json();
        self::assertSame('3.1.0', $openApi['openapi'] ?? null);
        self::assertSame('Packio API', $openApi['info']['title'] ?? null);

        $createTokenSchema = $openApi['paths']['/api/v1/user/api-tokens']['post']['requestBody']['content']['application/json']['schema'] ?? [];
        self::assertSame(['name'], $createTokenSchema['required'] ?? null);
        self::assertArrayHasKey('name', $createTokenSchema['properties'] ?? []);
        self::assertArrayHasKey('scopes', $createTokenSchema['properties'] ?? []);
        self::assertArrayHasKey('expires_at', $createTokenSchema['properties'] ?? []);

        $deleteTokenParameter = $openApi['paths']['/api/v1/user/api-tokens/{tokenId}']['delete']['parameters'][0] ?? [];
        self::assertSame('tokenId', $deleteTokenParameter['name'] ?? null);
        self::assertSame('path', $deleteTokenParameter['in'] ?? null);
        self::assertTrue($deleteTokenParameter['required'] ?? false);
        self::assertGreaterThanOrEqual(40, count($openApi['paths'] ?? []));
        self::assertArrayNotHasKey('/api/v1/organizations/{organization}/jobs', $openApi['paths'] ?? []);
        self::assertArrayNotHasKey('/api/v1/organizations/{organization}/jobs/stats', $openApi['paths'] ?? []);
        self::assertArrayNotHasKey('/api/v1/organizations/{organization}/jobs/{jobId}', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/sources/{sourceId}/sync', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/sources/{sourceId}/webhook-events', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/provider-authorizations/{authorizationId}/resources', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/provider-authorizations/{authorizationId}/reassign-delete', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/packages/{vendor}/{package}/releases/by-version', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/packages/{vendor}/{package}/releases/by-version/activity', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/organizations/{organization}/registry/overview', $openApi['paths'] ?? []);
        $this->assertAdminOpenApiSectionHidden($openApi);

        $this->get('/api/doc')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;
    }

    #[Test]
    public function itRestrictsDocumentationEndpointsWhenPublicAccessIsDisabled(): void
    {
        $this->disablePublicApiDocs();

        $this->get('/api/doc')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'authentication_required')
        ;

        $this->get('/api/doc.json')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'authentication_required')
        ;

        $this->get('/api/doc/assets/swagger-ui.css')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'authentication_required')
        ;

        $userDto = $this->createVerifiedUser();
        $this->logIn($userDto);

        $this->get('/api/doc')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;

        $testResponse = $this->get('/api/doc.json');
        $testResponse->assertStatus(200);
        $openApi = $testResponse->json();
        self::assertSame('3.1.0', $openApi['openapi'] ?? null);
        $this->assertAdminOpenApiSectionHidden($openApi);

        $this->get('/api/doc/assets/swagger-ui.css')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
        ;

        $this->clearCookies();

        $admin = $this->createVerifiedUser(['admin']);
        $this->logIn($admin);

        $adminResponse = $this->get('/api/doc.json');
        $adminResponse->assertStatus(200);
        $this->assertAdminOpenApiSectionVisible($adminResponse->json());
    }

    #[Test]
    public function itOnlyShowsAdminDocumentationSectionToAuthenticatedAdmins(): void
    {
        $userDto = $this->createVerifiedUser();
        $this->logIn($userDto);

        $testResponse = $this->get('/api/doc.json');
        $testResponse->assertStatus(200);
        $this->assertAdminOpenApiSectionHidden($testResponse->json());

        $this->clearCookies();

        $admin = $this->createVerifiedUser(['admin']);
        $this->logIn($admin);

        $adminResponse = $this->get('/api/doc.json');
        $adminResponse->assertStatus(200);
        $this->assertAdminOpenApiSectionVisible($adminResponse->json());
    }

    #[Test]
    public function itRejectsMissingAndInvalidApiTokens(): void
    {
        $this->get('/api/v1/me', [
            'Accept' => 'application/json',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'authentication_required')
        ;

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer invalid-token',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'Invalid API token.')
        ;
    }

    #[Test]
    public function itAuthenticatesCurrentUserWithBearerAndApiTokenHeaders(): void
    {
        $userDto  = $this->createVerifiedUser();
        $token    = $this->issueApiToken($userDto, ['read']);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $userDto->id)
            ->assertJsonPath('data.email', $userDto->email)
        ;

        $this->get('/api/v1/me', [
            'Accept'      => 'application/json',
            'X-API-TOKEN' => $token,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $userDto->id)
        ;
    }

    #[Test]
    public function itRecordsOnlyRejectedCredentialsInTheApiAuthenticationFailureQuota(): void
    {
        $userDto = $this->createVerifiedUser();
        $token   = $this->issueApiToken($userDto, ['read']);

        $this->get('/api/v1/me', [
            'Accept' => 'application/json',
        ])->assertStatus(401);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->assertStatus(200);

        self::assertSame([], $this->fakeRateLimiter->attempts);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer invalid-api-token',
        ])->assertStatus(401);

        self::assertCount(1, $this->fakeRateLimiter->attempts);

        /** @var array<int, array<string, int|string>> $attempts */
        $attempts = $this->fakeRateLimiter->attempts;
        self::assertStringStartsWith('api_authentication:ip:', (string) $attempts[0]['key']);
    }

    #[Test]
    public function itTracksLastUsedAtAndRejectsRevokedTokens(): void
    {
        $identity                  = $this->identity();
        $userDto                   = $this->createVerifiedUser();
        $issuedApiAccessTokenDto   = $identity->issueApiAccessTokenForUser($userDto->id, 'Tracked token', ['write']);

        self::assertSame('', $identity->apiAccessTokensForUser($userDto->id)[0]->lastUsedAt);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])->assertStatus(200);

        self::assertNotSame('', $identity->apiAccessTokensForUser($userDto->id)[0]->lastUsedAt);

        $this->request('DELETE', '/api/v1/user/api-tokens/' . $issuedApiAccessTokenDto->token->id, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])->assertStatus(204);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])->assertStatus(401);
    }

    #[Test]
    public function itRejectsUnknownScopesWhenCreatingAnApiToken(): void
    {
        $token = $this->issueApiToken($this->createVerifiedUser(), ['write']);

        $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Typo token',
            'scopes' => ['sources:raed'],
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.scopes.0', 'Unknown scope: sources:raed.')
        ;
    }

    #[Test]
    public function itRejectsTheUnusedAdminWildcardScopeAndNonStringLists(): void
    {
        $userDto = $this->createVerifiedUser();
        $admin   = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $organizationDto = $this->organization()->registerOrganization('List validation', $admin->id);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($admin, ['admin']),
        ];

        $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Wildcard',
            'scopes' => ['admin:*'],
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.details.scopes.0', 'Unknown scope: admin:*.')
        ;

        $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Mixed',
            'scopes' => ['read', 7],
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.details.scopes.0', 'Scopes must be a list of strings.')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/registry-tokens', [
            'name'        => 'Mixed',
            'permissions' => 'packages:read:private',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.details.permissions.0', 'Permissions must be a list of strings.')
        ;

        $this->request('PATCH', '/api/v1/admin/users/' . $admin->id, [
            'roles' => ['admin', true],
        ], $headers, true)
            ->assertStatus(422)
            ->assertJsonPath('error.details.roles.0', 'Roles must be a list of strings.')
        ;
    }

    #[Test]
    public function itRejectsASourceResourceTheProviderCannotParse(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Bad resource', $userDto->id);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($userDto, ['write']),
        ];

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider' => 'github',
            'resource' => 'owner/repo',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.details.source.0', 'Resource must be a repository URL supported by the selected provider.')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider' => 'no_such_provider',
            'resource' => 'https://example.test/owner/repo',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.details.source.0', 'Source provider is not available.')
        ;
    }

    #[Test]
    public function itImmediatelyBlocksAndRestoresAnExistingApiTokenWhenItsOwnerIsBannedAndUnbanned(): void
    {
        $identity                   = $this->identity();
        $moderator                  = $this->createVerifiedUser(['admin']);
        $userDto                    = $this->createVerifiedUser();
        $issuedApiAccessTokenDto    = $identity->issueApiAccessTokenForUser($userDto->id, 'Lifecycle token', ['read']);

        $identity->banUser($userDto->id, $moderator->id, 'Violation of project rules.');

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'account_banned')
            ->assertJsonPath('error.details.reason', 'Violation of project rules.')
        ;

        self::assertSame('', $identity->apiAccessTokensForUser($userDto->id)[0]->lastUsedAt);

        $identity->unbanUser($userDto->id, $moderator->id);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])->assertStatus(200);

        self::assertNotSame('', $identity->apiAccessTokensForUser($userDto->id)[0]->lastUsedAt);
    }

    #[Test]
    public function itRateLimitsApiAuthenticationAttemptsBeforeTokenStorageLookup(): void
    {
        $this->fakeRateLimiter->queueResult(false);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer invalid-api-token',
        ])
            ->assertStatus(429)
            ->assertJsonPath('error', 'Too many API authentication attempts. Please try again later.')
            ->assertHeader('Retry-After', '60')
        ;

        self::assertSame([], $this->fakeRateLimiter->attempts);
        $this->assertLastRateLimitCheck('api_authentication', 60, 60);
    }

    #[Test]
    public function itRejectsExpiredApiTokens(): void
    {
        $identity                = $this->identity();
        $userDto                 = $this->createVerifiedUser();
        $issuedApiAccessTokenDto = $identity->issueApiAccessTokenForUser(
            $userDto->id,
            'Expired token',
            ['read'],
            '2000-01-01T00:00:00+00:00',
        );

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $issuedApiAccessTokenDto->plainTextToken,
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'Invalid API token.')
        ;

        $storedToken = $identity->apiAccessTokensForUser($userDto->id)[0] ?? null;
        self::assertNotNull($storedToken);
        self::assertSame('', $storedToken->lastUsedAt);
    }

    #[Test]
    public function itRequiresVerifiedEmailForApiTokenAuthentication(): void
    {
        $userDto  = $this->createUser(verified: false);
        $token    = $this->issueApiToken($userDto, ['read']);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'email_verification_required')
        ;
    }

    #[Test]
    public function itCanListAndCreateApiTokensWithoutCsrf(): void
    {
        $userDto  = $this->createVerifiedUser();
        $token    = $this->issueApiToken($userDto, ['write']);

        $this->get('/api/v1/user/api-tokens', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Test API token')
        ;

        $testResponse = $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Automation token',
            'scopes' => ['read'],
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $testResponse
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Automation token')
            ->assertJsonPath('data.scopes.0', 'read')
        ;
        $createdTokenId = $testResponse->json()['data']['id'] ?? '';
        $createdToken   = $testResponse->json()['plain_token'] ?? '';
        self::assertIsString($createdTokenId);
        self::assertIsString($createdToken);
        self::assertNotSame('', $createdTokenId);
        self::assertNotSame('', $createdToken);

        $regenerated = $this->request('PUT', '/api/v1/user/api-tokens/' . $createdTokenId . '/regenerate', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $regenerated
            ->assertStatus(201)
            ->assertJsonPath('revoked_id', $createdTokenId)
            ->assertJsonPath('data.name', 'Automation token')
        ;
        $regeneratedToken   = $regenerated->json()['plain_token'] ?? '';
        $regeneratedTokenId = $regenerated->json()['data']['id'] ?? '';
        self::assertIsString($regeneratedToken);
        self::assertIsString($regeneratedTokenId);
        self::assertNotSame('', $regeneratedToken);
        self::assertNotSame('', $regeneratedTokenId);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $createdToken,
        ])->assertStatus(401);

        $this->get('/api/v1/me', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $regeneratedToken,
        ])->assertStatus(200);

        $this->request('DELETE', '/api/v1/user/api-tokens/' . $regeneratedTokenId, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->assertStatus(204);

        $apiTokenAuditEntries = $this->audit()->recentByOrganization('', 20);
        self::assertTrue(array_any($apiTokenAuditEntries, fn ($auditEntryDto) => 'api.v1.user.api-tokens.create' === $auditEntryDto->event
            && 'POST' === ($auditEntryDto->context['method'] ?? null)
            && 201 === ($auditEntryDto->context['status'] ?? null)
            && $userDto->id === ($auditEntryDto->context['user_id'] ?? null)
            && null !== ($auditEntryDto->context['api_token_id'] ?? null)));
        self::assertTrue(array_any($apiTokenAuditEntries, fn ($auditEntryDto) => 'api.v1.user.api-tokens.regenerate' === $auditEntryDto->event
            && 'PUT' === ($auditEntryDto->context['method'] ?? null)
            && $createdTokenId === ($auditEntryDto->context['route_params']['tokenId'] ?? null)));
        self::assertTrue(array_any($apiTokenAuditEntries, fn ($auditEntryDto) => 'api.v1.user.api-tokens.delete' === $auditEntryDto->event
            && 'DELETE' === ($auditEntryDto->context['method'] ?? null)
            && $regeneratedTokenId === ($auditEntryDto->context['route_params']['tokenId'] ?? null)));

        foreach ($apiTokenAuditEntries as $apiTokenAuditEntry) {
            if (! str_starts_with($apiTokenAuditEntry->event, 'api.v1.user.api-tokens.')) {
                continue;
            }

            self::assertArrayNotHasKey('plain_token', $apiTokenAuditEntry->context);
            self::assertArrayNotHasKey('request_body', $apiTokenAuditEntry->context);
        }
    }

    #[Test]
    public function itReturnsTheApiValidationEnvelopeAndDoesNotIssueATokenWhenSourceValidationFails(): void
    {
        $userDto = $this->createVerifiedUser();
        $token   = $this->issueApiToken($userDto, ['write']);
        $secret  = 'api-mapping-secret-' . bin2hex(random_bytes(6));

        $testResponse = $this->request('POST', '/api/v1/user/api-tokens', '"' . $secret . '"', [
            'Authorization' => 'Bearer ' . $token,
        ], true);

        $testResponse
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'Invalid request data.')
        ;

        $details = $testResponse->json()['error']['details'] ?? null;
        self::assertIsArray($details);
        self::assertArrayHasKey('name', $details);
        self::assertIsArray($details['name']);
        self::assertNotSame('', $details['name'][0] ?? null);
        self::assertStringNotContainsString($secret, $testResponse->body());
        self::assertCount(1, $this->identity()->apiAccessTokensForUser($userDto->id));
    }

    #[Test]
    public function itDoesNotIssueAdminApiScopesForRegularUsersThroughRestApi(): void
    {
        $userDto = $this->createVerifiedUser();
        $token   = $this->issueApiToken($userDto, ['write']);

        $testResponse = $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Escalation attempt',
            'scopes' => ['admin', 'admin:write', '*'],
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $testResponse
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Escalation attempt')
        ;
        self::assertSame(['read'], $testResponse->json()['data']['scopes'] ?? null);
    }

    #[Test]
    public function itRateLimitsUserApiTokenCreationWithJsonResponse(): void
    {
        $userDto = $this->createVerifiedUser();
        $token   = $this->issueApiToken($userDto, ['write']);

        $this->fakeRateLimiter->queueResults(true, false);

        $testResponse = $this->postJson('/api/v1/user/api-tokens', [
            'name'   => 'Rate limited token',
            'scopes' => ['read'],
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $this->assertJsonRateLimited(
            $testResponse,
            'Too many API token requests. Please try again later.',
            3600,
        );
        $this->assertLastRateLimitAttempt('api_token_create', 10, 3600);
    }

    #[Test]
    public function itRejectsAdminApiAccessForRegularUsersEvenWhenTokenHasAdminScope(): void
    {
        $userDto = $this->createVerifiedUser();
        $token   = $this->rawIssueApiToken($userDto, ['admin']);

        $this->get('/api/v1/admin/settings', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden')
        ;
    }

    #[Test]
    public function itRequiresAdminApiScopeForAdminUsers(): void
    {
        $userDto = $this->createVerifiedUser();
        $admin   = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $token = $this->issueApiToken($admin, ['read']);

        $this->get('/api/v1/admin/users', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'API token scope is not sufficient.')
        ;
    }

    #[Test]
    public function itRequiresOrganizationOwnerForOrganizationManagementApiActions(): void
    {
        $userDto         = $this->createVerifiedUser();
        $memberDto       = $this->createVerifiedUser();
        $otherMemberDto  = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Owner Guard API Team', $userDto->id);
        $this->organization()->addMember($organizationDto->id, $memberDto->id, 'member');
        $organizationMemberDto = $this->organization()->addMember($organizationDto->id, $otherMemberDto->id, 'member');
        $token                 = $this->issueApiToken($memberDto, ['write']);
        $headers               = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug, [
            'name'             => 'Compromised Team',
            'anonymous_access' => true,
        ], $headers, true)->assertStatus(403);

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/registry-tokens', [
            'name' => 'Compromised registry token',
        ], $headers)->assertStatus(403);

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug . '/members/' . $organizationMemberDto->id, [
            'role' => 'owner',
        ], $headers, true)->assertStatus(403);

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/invitations', [
            'role' => 'owner',
        ], $headers)->assertStatus(403);

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug, [], $headers)
            ->assertStatus(403)
        ;

        $organization = $this->organization()->findOrganizationBySlug($organizationDto->slug);
        self::assertNotNull($organization);
        self::assertSame('Owner Guard API Team', $organization->name);
        self::assertFalse($organization->anonymousAccess);
    }

    #[Test]
    public function itRejectsReservedOrganizationNamesFromApi(): void
    {
        $userDto  = $this->createVerifiedUser();
        $apiToken = $this->issueApiToken($userDto, ['write']);
        $headers  = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        $this->postJson('/api/v1/organizations', [
            'name' => 'Admin',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.name.0', 'Organization name is reserved.')
        ;
        self::assertNull($this->organization()->findOrganizationBySlug('admin'));

        $organizationDto = $this->organization()->registerOrganization('Reserved API Team ' . bin2hex(random_bytes(4)), $userDto->id);
        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug, [
            'name'             => 'App',
            'anonymous_access' => true,
        ], $headers, true)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.name.0', 'Organization name is reserved.')
        ;

        $reloaded = $this->organization()->findOrganization($organizationDto->id);
        self::assertNotNull($reloaded);
        self::assertSame($organizationDto->name, $reloaded->name);
        self::assertFalse($reloaded->anonymousAccess);
    }

    #[Test]
    public function itManagesOrganizationsAndRegistryTokens(): void
    {
        $userDto     = $this->createVerifiedUser();
        $apiToken    = $this->issueApiToken($userDto, ['write']);

        $testResponse = $this->postJson('/api/v1/organizations', [
            'name' => 'API Team',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $testResponse
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'API Team')
        ;
        $slug = $testResponse->json()['data']['slug'] ?? '';
        self::assertIsString($slug);
        self::assertNotSame('', $slug);
        $organizationId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($organizationId);
        self::assertNotSame('', $organizationId);
        self::assertSame(
            1,
            count(array_filter(
                $this->audit()->recentByOrganization('', 20),
                fn ($auditEntryDto): bool => 'api.v1.organizations.create' === $auditEntryDto->event,
            )),
            'Each audited request must produce exactly one audit entry.',
        );

        $this->get('/api/v1/organizations', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.slug', $slug)
        ;

        $this->request('PATCH', '/api/v1/organizations/' . $slug, [
            'name'             => 'API Team Updated',
            'anonymous_access' => true,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ], true)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'API Team Updated')
            ->assertJsonPath('data.anonymous_access', true)
        ;
        self::assertTrue($this->hasAuditEntry($organizationId, 'api.v1.organizations.update', fn ($auditEntryDto): bool => 'PATCH' === ($auditEntryDto->context['method'] ?? null)
            && 200 === ($auditEntryDto->context['status'] ?? null)
            && $slug === ($auditEntryDto->context['route_params']['organization'] ?? null)));
        self::assertSame(
            1,
            count(array_filter(
                $this->audit()->recentByOrganization($organizationId, 20),
                fn ($auditEntryDto): bool => 'api.v1.organizations.update' === $auditEntryDto->event,
            )),
            'Each audited request must produce exactly one audit entry.',
        );

        $registryToken = $this->postJson('/api/v1/organizations/' . $slug . '/registry-tokens', [
            'name' => 'Composer automation',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $registryToken
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Composer automation')
        ;
        self::assertNotSame('', $registryToken->json()['plain_token'] ?? '');
        $registryTokenId = $registryToken->json()['data']['id'] ?? '';
        self::assertIsString($registryTokenId);
        self::assertNotSame('', $registryTokenId);

        $registryTokens = $this->get('/api/v1/organizations/' . $slug . '/registry-tokens', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $registryTokens
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $registryTokenId)
        ;
        self::assertArrayNotHasKey('plain_token', $registryTokens->json()['data'][0] ?? []);

        $regenerated = $this->request('PUT', '/api/v1/organizations/' . $slug . '/registry-tokens/' . $registryTokenId . '/regenerate', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $regenerated
            ->assertStatus(201)
            ->assertJsonPath('revoked_id', $registryTokenId)
            ->assertJsonPath('data.name', 'Composer automation')
        ;
        self::assertNotSame('', $regenerated->json()['plain_token'] ?? '');
        $regeneratedRegistryTokenId = $regenerated->json()['data']['id'] ?? '';
        self::assertIsString($regeneratedRegistryTokenId);
        self::assertNotSame('', $regeneratedRegistryTokenId);
        self::assertNotSame($registryTokenId, $regeneratedRegistryTokenId);

        $this->request('DELETE', '/api/v1/organizations/' . $slug . '/registry-tokens/' . $regeneratedRegistryTokenId, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(204);
        $hasRegistryTokenDeleteAudit = array_any($this->audit()->recentByOrganization($organizationId, 10), fn ($auditEntryDto) => 'api.v1.organizations.registry-tokens.delete' === $auditEntryDto->event
        && 'DELETE' === ($auditEntryDto->context['method'] ?? null)
        && 204 === ($auditEntryDto->context['status'] ?? null));

        self::assertTrue($hasRegistryTokenDeleteAudit);
    }

    #[Test]
    public function itHidesRegistryTokensOutsideTheRegenerationOwnerAndOrganization(): void
    {
        $userDto                             = $this->createVerifiedUser();
        $otherUser                           = $this->createVerifiedUser();
        $organizationDto                     = $this->organization()->registerOrganization('regeneration-primary', $userDto->id);
        $otherOrganization                   = $this->organization()->registerOrganization('regeneration-secondary', $userDto->id);
        $commands                            = $this->getService(IdentityCommandService::class);
        $issuedRegistryAccessTokenDto        = $commands->issueRegistryTokenForUser($organizationDto->id, $otherUser->id, 'Other owner');
        $foreignOrganizationToken            = $commands->issueRegistryTokenForUser($otherOrganization->id, $userDto->id, 'Other organization');
        $apiToken                            = $this->issueApiToken($userDto, ['write']);

        foreach ([$issuedRegistryAccessTokenDto->id, $foreignOrganizationToken->id, Uuid::uuid7()->toString()] as $tokenId) {
            $this->request('PUT', '/api/v1/organizations/' . $organizationDto->slug . '/registry-tokens/' . $tokenId . '/regenerate', [], [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $apiToken,
            ])->assertStatus(404);
        }

        self::assertNotNull($this->identity()->authenticateRegistryAccessToken($issuedRegistryAccessTokenDto->plainTextToken));
        self::assertNotNull($this->identity()->authenticateRegistryAccessToken($foreignOrganizationToken->plainTextToken));
    }

    #[Test]
    public function itListsPackagesAndReleases(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Package API Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);

        $this->packages()->upsert(new PackageDto(
            name: 'acme/demo',
            type: 'library',
            description: 'Demo package',
            visibility: 'private',
            sourceType: 'github',
            sourceUrl: 'https://github.com/acme/demo',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationDto->id,
            active: true,
            sourceId: 'source-1',
            downloadCount: 7,
        ));
        $this->releases()->publish(new ReleaseDto(
            packageName: 'acme/demo',
            version: '1.0.0',
            reference: 'abc123',
            composer: [
                'name'    => 'acme/demo',
                'version' => '1.0.0',
            ],
            source: [
                'type' => 'git',
                'url'  => 'https://github.com/acme/demo',
            ],
            dist: [],
            createdAt: '',
            organizationId: $organizationDto->id,
            releasedAt: '2026-06-27T00:00:00+00:00',
            active: true,
            downloadCount: 3,
        ));

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/packages', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'acme/demo')
            ->assertJsonPath('data.0.active', true)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'acme/demo')
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo/releases', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.version', '1.0.0')
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo/releases/by-version?version=1.0.0', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.reference', 'abc123')
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/registry/overview', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'acme/demo')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo/releases/by-version/activity?version=1.0.0', [
            'active' => false,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.version', '1.0.0')
            ->assertJsonPath('data.active', false)
        ;

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo/releases', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', 1)
        ;
        self::assertTrue($this->hasAuditEntry($organizationDto->id, 'api.v1.organizations.packages.releases.delete', fn ($auditEntryDto): bool => 'DELETE' === ($auditEntryDto->context['method'] ?? null)
            && 'acme/demo' === ($auditEntryDto->context['package_name'] ?? null)));

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(204);
        self::assertTrue($this->hasAuditEntry($organizationDto->id, 'api.v1.organizations.packages.delete', fn ($auditEntryDto): bool => 'DELETE' === ($auditEntryDto->context['method'] ?? null)
            && 204 === ($auditEntryDto->context['status'] ?? null)
            && 'acme/demo' === ($auditEntryDto->context['package_name'] ?? null)));

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/packages/acme/demo', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(404);
    }

    #[Test]
    public function itManagesOrganizationMembersAndInvitations(): void
    {
        $userDto                     = $this->createVerifiedUser();
        $memberUser                  = $this->createVerifiedUser();
        $organizationDto             = $this->organization()->registerOrganization('Member API Team', $userDto->id);
        $organizationMemberDto       = $this->organization()->addMember($organizationDto->id, $memberUser->id);
        $apiToken                    = $this->issueApiToken($userDto, ['write']);

        $testResponse = $this->get('/api/v1/organizations/' . $organizationDto->slug . '/members', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $testResponse->assertStatus(200);
        self::assertContains($organizationMemberDto->id, array_column($testResponse->json()['data'] ?? [], 'id'));

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug . '/members/' . $organizationMemberDto->id, [
            'role' => 'owner',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ], true)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $organizationMemberDto->id)
            ->assertJsonPath('data.role', 'owner')
        ;

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/members/' . $organizationMemberDto->id, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(204);

        $createdInvitation = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/invitations', [
            'role' => 'member',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $createdInvitation
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.status', 'pending')
        ;
        $invitationId = $createdInvitation->json()['data']['id'] ?? '';
        self::assertIsString($invitationId);
        self::assertNotSame('', $invitationId);
        self::assertNotSame('', $createdInvitation->json()['data']['token'] ?? '');

        $invitations = $this->get('/api/v1/organizations/' . $organizationDto->slug . '/invitations', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $invitations
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $invitationId)
        ;
        self::assertArrayNotHasKey('token', $invitations->json()['data'][0] ?? []);

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/invitations/' . $invitationId, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invitation_pending')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/invitations/' . $invitationId . '/revoke', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(204);

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/invitations/' . $invitationId, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])->assertStatus(204);
    }

    #[Test]
    public function migratedFamiliesRejectInvalidRequestsBeforeTheirHandlers(): void
    {
        $userDto         = $this->createVerifiedUser(['user', 'admin']);
        $organizationDto = $this->organization()->registerOrganization('Validation families', $userDto->id);
        $token           = $this->issueApiToken($userDto, ['write', 'admin']);
        $base            = '/api/v1/organizations/' . $organizationDto->slug;
        $cases           = [
            [OrganizationUpdateHandler::class, 'PATCH', $base, [
                'name' => ' ',
            ], 'name'],
            [OrganizationMemberUpdateHandler::class, 'PATCH', $base . '/members/missing', [
                'role' => 'admin',
            ], 'role'],
            [OrganizationInvitationsCreateHandler::class, 'POST', $base . '/invitations', [], 'role'],
            [ProviderAuthorizationsCreateHandler::class, 'POST', $base . '/provider-authorizations', [
                'provider' => 'github',
                'payload'  => false,
            ], 'payload'],
            [SourceProviderInstancesCreateHandler::class, 'POST', $base . '/source-provider-instances', [], 'source_provider_instance'],
            [SourceProviderInstanceUpdateHandler::class, 'PATCH', $base . '/source-provider-instances/missing', [], 'source_provider_instance'],
            [AdminOrganizationUpdateHandler::class, 'PATCH', '/api/v1/admin/organizations/' . $organizationDto->id, [
                'slug' => 'bad/slug',
            ], 'slug'],
            [AdminUserUpdateHandler::class, 'PATCH', '/api/v1/admin/users/' . $userDto->id, [
                'email' => 'invalid',
            ], 'email'],
            [AdminSettingsUpdateHandler::class, 'PATCH', '/api/v1/admin/settings', [
                'settings' => false,
            ], 'settings'],
            [ApiAccessTokensCreateHandler::class, 'POST', '/api/v1/user/api-tokens', [], 'name'],
            [RegistryTokensCreateHandler::class, 'POST', $base . '/registry-tokens', [], 'name'],
            [ReleaseActivityHandler::class, 'POST', $base . '/packages/vendor/package/releases/by-version/activity?version=v1', [
                'active' => 'invalid',
            ], 'active'],
        ];
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        foreach ($cases as [$class]) {
            $this->serviceOverrides[$class] = $handler;
        }
        $this->rebuildContainer();
        $this->rebuildApplication();
        foreach ($cases as [$class, $method, $uri, $payload, $field]) {
            $response = $this->request($method, $uri, $payload, [
                'Authorization' => 'Bearer ' . $token,
            ], true);
            $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
            self::assertArrayHasKey($field, $response->json()['error']['details'], $class);
        }
    }

    #[Test]
    public function sourceCreationValidationPreservesTheEnvelopeAndStopsBeforeTheHandler(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Create validation', $userDto->id);
        $token           = $this->issueApiToken($userDto, ['write']);
        $handler         = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $this->serviceOverrides[SourcesCreateHandler::class] = $handler;
        $this->rebuildContainer();
        $this->rebuildApplication();

        foreach ([
            [[], [
                'provider' => ['Provider is required.'],
                'resource' => ['Resource is required.'],
            ]],
            [[
                'provider' => 'github',
            ], [
                'provider' => [],
                'resource' => ['Resource is required.'],
            ]],
            [[
                'resource' => 'repo',
            ], [
                'provider' => ['Provider is required.'],
                'resource' => [],
            ]],
            [[
                'visibility' => 'secret',
            ], [
                'visibility' => ['Visibility must be public or private.'],
            ]],
            [[
                'keep_last_releases' => -1,
            ], [
                'keep_last_releases' => ['Keep last releases must be a non-negative integer.'],
            ]],
            [[
                'settings' => 'invalid',
            ], [
                'settings' => ['Settings must be an object.'],
            ]],
        ] as [$input, $details]) {
            if (! isset($details['provider'])) {
                $input += [
                    'provider' => 'github',
                    'resource' => 'repo',
                ];
            }
            $response = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', $input, [
                'Authorization' => 'Bearer ' . $token,
            ]);
            $response->assertStatus(422);
            self::assertSame([
                'error' => [
                    'code'    => 'validation_failed',
                    'message' => 'Invalid request data.',
                    'details' => $details,
                ],
            ], $response->json());
        }
    }

    #[Test]
    public function sourceSettingsValidationRejectsInvalidDataBeforeEitherHandler(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Settings validation', $userDto->id);
        $token           = $this->issueApiToken($userDto, ['write']);
        $source          = $this->getService(SourceApiFacadeInterface::class)->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/settings-validation',
            organizationId: $organizationDto->id,
        ));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $this->serviceOverrides[SourceUpdateHandler::class]         = $handler;
        $this->serviceOverrides[SourceSettingsUpdateHandler::class] = $handler;
        $this->rebuildContainer();
        $this->rebuildApplication();
        $this->logIn($userDto);
        $webUrl = '/organization/' . $organizationDto->slug . '/sources/' . $source->id;
        $apiUrl = '/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $source->id;
        foreach ([
            ['visibility', 'secret', 'Visibility must be public or private.'],
            ['active', 'invalid', 'Active must be a boolean.'],
            ['keep_last_releases', -1, 'Keep last releases must be a non-negative integer.'],
            ['active', null, 'Active must be a boolean.'],
        ] as [$field, $value, $message]) {
            $this->request('PATCH', $apiUrl, [
                $field => $value,
            ], [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ], true)->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonPath('error.details.' . $field . '.0', $message)
            ;
            $this->withCsrf()->post($webUrl . '/settings', [
                $field => $value,
            ])->assertRedirect($webUrl);
            $page = $this->get($webUrl, [
                'Accept'    => 'application/json',
                'X-Inertia' => 'true',
            ])->json();
            self::assertSame('Invalid request data.', $page['flash']['error'] ?? null);
        }
        self::assertSame('public', $this->getService(SourceApiFacadeInterface::class)->findSource($source->id)->visibility);
    }

    #[Test]
    public function sourceSettingsWebAndApiHaveTheSamePatchSemantics(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Settings contract', $userDto->id);
        $token           = $this->issueApiToken($userDto, ['write']);
        $sources         = $this->getService(SourceApiFacadeInterface::class);
        $packages        = $this->getService(PackageApiFacadeInterface::class);
        $this->logIn($userDto);
        foreach (['web', 'api'] as $transport) {
            $source = $sources->registerSource(new SourceRegistrationRequestDto(
                provider: 'github',
                resource: 'https://github.com/org/contract-' . $transport,
                organizationId: $organizationDto->id,
            ));
            $packages->upsert(new PackageDto(name: 'contract/' . $transport, type: 'library', description: '', visibility: 'public', sourceType: 'github', sourceUrl: '', createdAt: '', updatedAt: '', organizationId: $organizationDto->id, sourceId: $source->id));
            $webUrl = '/organization/' . $organizationDto->slug . '/sources/' . $source->id;
            $apiUrl = '/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $source->id;
            foreach ([
                [[
                    'visibility'       => 'private',
                    'active'           => '0',
                    'keepLastReleases' => '5',
                ], ['private', false, 5]],
                [[
                    'visibility' => 'public',
                ], ['public', false, 5]],
                [[
                    'active'             => 'on',
                    'keep_last_releases' => '0',
                ], ['public', true, 0]],
                [[], ['public', true, 0]],
            ] as [$payload, $expected]) {
                if ('web' === $transport) {
                    $this->withCsrf()->post($webUrl . '/settings', $payload)->assertRedirect($webUrl);
                    $page = $this->get($webUrl, [
                        'Accept'    => 'application/json',
                        'X-Inertia' => 'true',
                    ])->json();
                    self::assertSame('Source settings updated.', $page['flash']['success'] ?? null);
                } else {
                    $this->request('PATCH', $apiUrl, $payload, [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ], true)->assertStatus(200);
                }
                $updated = $sources->findSource($source->id);
                self::assertSame($expected, [$updated->visibility, $updated->active, $updated->keepLastReleases], $transport);
                self::assertSame($expected[0], $packages->findPackageInOrganization($organizationDto->id, 'contract/' . $transport)->visibility);
            }
            $invalid = [
                'visibility'       => 'private',
                'keepLastReleases' => '-1',
            ];
            if ('web' === $transport) {
                $this->withCsrf()->post($webUrl . '/settings', $invalid)->assertRedirect($webUrl);
                $page = $this->get($webUrl, [
                    'Accept'    => 'application/json',
                    'X-Inertia' => 'true',
                ])->json();
                self::assertSame('Invalid request data.', $page['flash']['error'] ?? null);
            } else {
                $this->request('PATCH', $apiUrl, $invalid, [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ], true)->assertStatus(422);
            }
            self::assertSame('public', $sources->findSource($source->id)->visibility);
        }
    }

    #[Test]
    public function itHidesWebhookSecretFromRegularMembersInSourcesApi(): void
    {
        $userDto         = $this->createVerifiedUser();
        $member          = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Secret API Team', $userDto->id);
        $this->organization()->addMember($organizationDto->id, $member->id, 'member');
        $ownerToken  = $this->issueApiToken($userDto, ['write']);
        $memberToken = $this->issueApiToken($member, ['read']);
        $base        = '/api/v1/organizations/' . $organizationDto->slug . '/sources';

        $sourceId = $this->postJson($base, [
            'provider'           => 'github',
            'resource'           => 'https://github.com/acme/secret-' . bin2hex(random_bytes(4)),
            'visibility'         => 'private',
            'default_ref'        => 'main',
            'keep_last_releases' => 2,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $ownerToken,
        ])->json()['data']['id'];

        $ownerShow = $this->get($base . '/' . $sourceId, [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $ownerToken,
        ])->json();
        self::assertNotSame('', $ownerShow['data']['provider_config']['webhook_secret'] ?? '');

        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $memberToken,
        ];
        $index   = $this->get($base, $headers)->assertStatus(200)->json();
        $show    = $this->get($base . '/' . $sourceId, $headers)->assertStatus(200)->json();

        self::assertArrayNotHasKey('webhook_secret', $index['data'][0]['provider_config']);
        self::assertArrayNotHasKey('webhook_secret', $show['data']['provider_config']);
    }

    #[Test]
    public function itExposesWebhookSecretOnlyToWriteScopedTokens(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Scoped Secret Team', $userDto->id);
        $writeToken      = $this->issueApiToken($userDto, ['write']);
        $readHeaders     = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($userDto, ['read']),
        ];
        $base            = '/api/v1/organizations/' . $organizationDto->slug . '/sources';

        $sourceId = $this->postJson($base, [
            'provider'           => 'github',
            'resource'           => 'https://github.com/acme/scoped-' . bin2hex(random_bytes(4)),
            'visibility'         => 'private',
            'default_ref'        => 'main',
            'keep_last_releases' => 2,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $writeToken,
        ])->json()['data']['id'];

        $writeHeaders = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $writeToken,
        ];
        self::assertNotSame('', $this->get($base . '/' . $sourceId, $writeHeaders)->json()['data']['provider_config']['webhook_secret'] ?? '');
        self::assertNotSame('', $this->get($base, $writeHeaders)->json()['data'][0]['provider_config']['webhook_secret'] ?? '');

        self::assertArrayNotHasKey('webhook_secret', $this->get($base . '/' . $sourceId, $readHeaders)->assertStatus(200)->json()['data']['provider_config']);
        self::assertArrayNotHasKey('webhook_secret', $this->get($base, $readHeaders)->assertStatus(200)->json()['data'][0]['provider_config']);

        $sourcesReadHeaders = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($userDto, ['sources:read']),
        ];
        self::assertArrayNotHasKey('webhook_secret', $this->get($base . '/' . $sourceId, $sourcesReadHeaders)->assertStatus(200)->json()['data']['provider_config']);
        self::assertArrayNotHasKey('webhook_secret', $this->get($base, $sourcesReadHeaders)->assertStatus(200)->json()['data'][0]['provider_config']);

        $sourcesAllHeaders = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($userDto, ['sources:*']),
        ];
        self::assertNotSame('', $this->get($base . '/' . $sourceId, $sourcesAllHeaders)->assertStatus(200)->json()['data']['provider_config']['webhook_secret'] ?? '');
        self::assertNotSame('', $this->get($base, $sourcesAllHeaders)->assertStatus(200)->json()['data'][0]['provider_config']['webhook_secret'] ?? '');
    }

    #[Test]
    public function itManagesSources(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Source API Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);

        $this->get('/api/v1/source-providers')
            ->assertStatus(200)
        ;

        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'           => 'generic_git',
            'resource'           => 'https://git.example.test/acme/api-source-' . bin2hex(random_bytes(4)) . '.git',
            'visibility'         => 'private',
            'default_ref'        => 'main',
            'keep_last_releases' => 2,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ]);
        $testResponse
            ->assertStatus(201)
            ->assertJsonPath('data.provider', 'generic_git')
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.archived', false)
            ->assertJsonPath('data.keep_last_releases', 2)
        ;
        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $sourceId)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId, [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sourceId)
        ;

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId, [
            'visibility'         => 'public',
            'active'             => false,
            'keep_last_releases' => 1,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ], true)
            ->assertStatus(200)
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.keep_last_releases', 1)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/sync', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'source_inactive')
        ;

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId, [
            'active' => true,
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ], true)
            ->assertStatus(200)
            ->assertJsonPath('data.active', true)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/sync', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
        ;
        self::assertTrue($this->hasAuditEntry($organizationDto->id, 'api.v1.organizations.sources.sync', fn ($auditEntryDto): bool => 'POST' === ($auditEntryDto->context['method'] ?? null)
            && 202 === ($auditEntryDto->context['status'] ?? null)
            && $sourceId === ($auditEntryDto->context['source_id'] ?? null)));

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/archive', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.archived', true)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/restore', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.archived', false)
        ;

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId, [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true)
        ;
    }

    #[Test]
    public function itRateLimitsSourceSyncWithJsonResponse(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Source Sync Rate Limit Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        $sourceResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/rate-limited-source-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $sourceResponse->assertStatus(201);
        $sourceId = $sourceResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);

        $this->fakeRateLimiter->queueResults(true, false);

        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/sync', [], $headers);

        $this->assertJsonRateLimited(
            $testResponse,
            'Too many API queue action requests. Please try again later.',
            60,
        );
        $this->assertLastRateLimitAttempt('api_queue_action', 30, 60);
    }

    #[Test]
    public function itManagesSourceProvidersAndAuthorizations(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Provider API Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances', [
            'provider_type' => 'gitlab_self_hosted',
            'label'         => 'GitLab API',
            'base_url'      => 'https://gitlab-' . bin2hex(random_bytes(4)) . '.example.test',
            'auth_mode'     => 'token',
        ], $headers);
        $testResponse
            ->assertStatus(201)
            ->assertJsonPath('data.provider_type', 'gitlab_self_hosted')
            ->assertJsonPath('data.status', 'active')
        ;
        $instanceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($instanceId);
        self::assertNotSame('', $instanceId);

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances?provider_type=gitlab_self_hosted', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $instanceId)
        ;

        $invalidProviderType = $this->get('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances?provider_type=github', $headers);
        $invalidProviderType
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'source_provider_instance_invalid')
            ->assertJsonPath('error.message', 'Source provider instance input or state is invalid.')
        ;
        self::assertStringStartsWith('application/json', $invalidProviderType->header('Content-Type'));
        self::assertStringNotContainsString('<!DOCTYPE html>', $invalidProviderType->body());

        $this->request('PATCH', '/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances/' . $instanceId, [
            'provider_type' => 'gitlab_self_hosted',
            'label'         => 'GitLab API Updated',
            'base_url'      => $testResponse->json()['data']['base_url'],
            'auth_mode'     => 'token',
        ], $headers, true)
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'GitLab API Updated')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances/' . $instanceId . '/deactivate', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances/' . $instanceId . '/activate', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
        ;

        $authorization = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations', [
            'provider'      => 'generic_git',
            'auth_strategy' => 'token',
            'payload'       => [
                'token'       => 'secret-token',
                'auth_scheme' => 'bearer',
            ],
        ], $headers);
        $authorization
            ->assertStatus(201)
            ->assertJsonPath('data.provider', 'generic_git')
            ->assertJsonPath('data.configured', true)
        ;
        $authorizationId = $authorization->json()['data']['id'] ?? '';
        self::assertIsString($authorizationId);
        self::assertNotSame('', $authorizationId);
        self::assertArrayNotHasKey('token', $authorization->json()['data'] ?? []);

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations?provider=generic_git', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $authorizationId)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations/' . $authorizationId . '/resources?provider=generic_git', $headers)
            ->assertStatus(200)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations/' . $authorizationId . '/dependencies', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.has_sources', false)
        ;

        $createdSource = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/webhook-source-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $createdSource->assertStatus(201);
        $sourceId = $createdSource->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/webhook/register', [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'webhook_auto_registration_not_supported')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/webhook/rotate-secret', [], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sourceId)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/provider-authorizations/' . $authorizationId . '/reassign-delete', [
            'new_authorization_id' => null,
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true)
        ;

        $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/source-provider-instances/' . $instanceId, [], $headers)
            ->assertStatus(204)
        ;
    }

    #[Test]
    public function itManagesSourceWebhookEvents(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Webhook API Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        $testResponse = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'github',
            'resource'   => 'https://github.com/acme/webhook-api-source-' . bin2hex(random_bytes(4)),
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);
        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);

        $webhookEvent = WebhookEvent::receive(
            $sourceId,
            'github',
            'delivery-' . bin2hex(random_bytes(4)),
            'push',
            'payload-hash',
        );
        $webhookEvent->markRejected('Signature mismatch.');
        $this->webhookEvents()->save($webhookEvent);

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/webhook-events', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.0.delivery_id', $webhookEvent->deliveryId())
            ->assertJsonPath('data.0.status', 'rejected')
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/webhook-events/' . $webhookEvent->deliveryId() . '/reprocess', [], $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.provider', 'github')
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/sources/' . $sourceId . '/webhook-events', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.0.status', 'processed')
            ->assertJsonPath('data.0.reprocess_count', 1)
        ;
    }

    #[Test]
    public function itReturnsNotFoundForFormerOrganizationJobsApiRoutes(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Removed Jobs API Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];
        $testResponse  = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/removed-jobs-source-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);
        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        $jobDto = $this->jobs()->enqueue($organizationDto->id, 'source.sync', [
            'source_id' => $sourceId,
        ]);

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/jobs', $headers)
            ->assertStatus(404)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/jobs/stats', $headers)
            ->assertStatus(404)
        ;

        $this->get('/api/v1/organizations/' . $organizationDto->slug . '/jobs/' . $jobDto->id, $headers)
            ->assertStatus(404)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/jobs/' . $jobDto->id . '/retry', [], $headers)
            ->assertStatus(404)
        ;

        $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/jobs/' . $jobDto->id . '/cancel', [], $headers)
            ->assertStatus(404)
        ;
    }

    #[Test]
    public function itManagesAdminJobsThroughTheAdminApi(): void
    {
        $userDto         = $this->createVerifiedUser(['user', 'admin']);
        $organizationDto = $this->organization()->registerOrganization('Admin Jobs API Team', $userDto->id);
        $jobDto          = $this->jobs()->enqueue($organizationDto->id, 'source.sync', [
            'source_id' => 'admin-jobs-api-source',
        ]);
        $adminToken      = $this->issueApiToken($userDto, ['admin']);
        $adminHeaders    = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $adminToken,
        ];

        $this->get('/api/v1/admin/jobs', $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $jobDto->id)
        ;

        $this->get('/api/v1/admin/jobs/' . $jobDto->id, $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $jobDto->id)
        ;

        $testResponse = $this->postJson('/api/v1/admin/jobs/' . $jobDto->id . '/restart', [], $adminHeaders);
        $testResponse
            ->assertStatus(202)
            ->assertJsonPath('data.type', 'source.sync')
        ;
        self::assertNotSame($jobDto->id, $testResponse->json()['data']['id'] ?? '');
        self::assertTrue($this->hasAuditEntry('', 'api.v1.admin.jobs.restart', fn ($auditEntryDto): bool => 'POST' === ($auditEntryDto->context['method'] ?? null)
            && 202 === ($auditEntryDto->context['status'] ?? null)
            && $jobDto->id === ($auditEntryDto->context['route_params']['jobId'] ?? null)
            && null !== ($auditEntryDto->context['api_token_id'] ?? null)));
    }

    #[Test]
    public function itManagesAdminUsersOrganizationsPackagesSourcesAndSettings(): void
    {
        $userDto         = $this->createVerifiedUser();
        $unverifiedUser  = $this->createUser(verified: false);
        $organizationDto = $this->organization()->registerOrganization('Admin Surfaces Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];
        $testResponse  = $this->postJson('/api/v1/organizations/' . $organizationDto->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/admin-source-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);
        $sourceId = $testResponse->json()['data']['id'] ?? '';
        self::assertIsString($sourceId);
        self::assertNotSame('', $sourceId);
        $this->packages()->upsert(new PackageDto(
            name: 'admin/demo',
            type: 'library',
            description: 'Admin demo package',
            visibility: 'private',
            sourceType: 'generic_git',
            sourceUrl: 'https://git.example.test/acme/admin-demo.git',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationDto->id,
            active: true,
            sourceId: $sourceId,
            downloadCount: 0,
        ));

        $admin = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $adminToken   = $this->issueApiToken($admin, ['admin']);
        $adminHeaders = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $adminToken,
        ];

        $adminUsers = $this->get('/api/v1/admin/users', $adminHeaders);
        $adminUsers->assertStatus(200);
        self::assertContains($userDto->id, array_column($adminUsers->json()['data'] ?? [], 'id'));

        $this->get('/api/v1/admin/users/' . $unverifiedUser->id, $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.is_email_verified', false)
        ;

        $this->request('PATCH', '/api/v1/admin/users/' . $unverifiedUser->id, [
            'email'        => $unverifiedUser->email,
            'display_name' => 'Updated API User',
            'roles'        => ['user'],
        ], $adminHeaders, true)
            ->assertStatus(200)
            ->assertJsonPath('data.display_name', 'Updated API User')
        ;

        $this->postJson('/api/v1/admin/users/' . $unverifiedUser->id . '/verify', [], $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.is_email_verified', true)
        ;

        $adminOrganizations = $this->get('/api/v1/admin/organizations', $adminHeaders);
        $adminOrganizations->assertStatus(200);
        self::assertContains($organizationDto->id, array_column($adminOrganizations->json()['data'] ?? [], 'id'));

        $this->get('/api/v1/admin/organizations/' . $organizationDto->id, $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $organizationDto->id)
        ;

        $this->request('PATCH', '/api/v1/admin/organizations/' . $organizationDto->id, [
            'name'             => 'Admin Surfaces Team Updated',
            'slug'             => $organizationDto->slug,
            'anonymous_access' => false,
        ], $adminHeaders, true)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Admin Surfaces Team Updated')
        ;

        $this->get('/api/v1/admin/packages', $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'admin/demo')
        ;

        $this->get('/api/v1/admin/sources', $adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $sourceId)
        ;

        $this->get('/api/v1/admin/settings', $adminHeaders)
            ->assertStatus(200)
        ;

        $settings = $this->request('PATCH', '/api/v1/admin/settings', [
            'settings' => [
                ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN => RuntimeSettingOverride::Off->value,
                ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC   => RuntimeSettingOverride::Off->value,
            ],
        ], $adminHeaders, true)
            ->assertStatus(200)
        ;
        self::assertSame(RuntimeSettingOverride::Off->value, $settings->json()['data']['values'][ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN]['override'] ?? null);
        self::assertSame(RuntimeSettingOverride::Off->value, $settings->json()['data']['values'][ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC]['override'] ?? null);
        self::assertTrue($this->hasAuditEntry('', 'api.v1.admin.settings.update', fn ($auditEntryDto): bool => 'PATCH' === ($auditEntryDto->context['method'] ?? null)
            && 200 === ($auditEntryDto->context['status'] ?? null)
            && $admin->id === ($auditEntryDto->context['user_id'] ?? null)));

        $organizationToDelete = $this->organization()->registerOrganization('Admin Delete API Team', $userDto->id);
        $this->request('DELETE', '/api/v1/admin/organizations/' . $organizationToDelete->id, [], $adminHeaders)
            ->assertStatus(204)
        ;
    }

    #[Test]
    public function itRejectsRemovingAdminRoleFromTheLastActiveAdminViaApi(): void
    {
        $userDto = $this->createVerifiedUser();
        $admin   = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($admin, ['admin']),
        ];

        $this->request('PATCH', '/api/v1/admin/users/' . $admin->id, [
            'roles' => ['user'],
        ], $headers, true)
            ->assertStatus(422)
            ->assertJsonPath('error.details.roles.0', 'The last active administrator cannot lose the admin role.')
        ;
        $after = $this->identity()->findUserById($admin->id);
        self::assertInstanceOf(UserDto::class, $after);
        self::assertContains('admin', $after->roles);
    }

    #[Test]
    public function itRejectsUnknownRolesViaAdminApi(): void
    {
        $userDto = $this->createVerifiedUser();
        $admin   = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($admin, ['admin']),
        ];

        $this->request('PATCH', '/api/v1/admin/users/' . $admin->id, [
            'roles' => ['admni'],
        ], $headers, true)
            ->assertStatus(422)
            ->assertJsonPath('error.details.roles.0', 'Unknown role: admni.')
        ;
        $after = $this->identity()->findUserById($admin->id);
        self::assertInstanceOf(UserDto::class, $after);
        self::assertContains('admin', $after->roles);
    }

    #[Test]
    public function itRejectsAnEmailAlreadyUsedByAnotherUserViaAdminApi(): void
    {
        $userDto = $this->createVerifiedUser();
        $admin   = $this->identity()->updateUser(
            ($user = $this->createVerifiedUser())->id,
            $user->email,
            $user->displayName,
            ['user', 'admin'],
        );
        self::assertInstanceOf(UserDto::class, $admin);
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->issueApiToken($admin, ['admin']),
        ];

        $this->request('PATCH', '/api/v1/admin/users/' . $admin->id, [
            'email' => $userDto->email,
        ], $headers, true)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.email', ['Email is already in use.'])
        ;
        $after = $this->identity()->findUserById($admin->id);
        self::assertInstanceOf(UserDto::class, $after);
        self::assertSame($admin->email, $after->email);
    }

    #[Test]
    public function itRateLimitsAdminJobRestartWithJsonResponse(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Admin Job Rate Limit Team', $userDto->id);
        $jobDto          = $this->jobs()->enqueue($organizationDto->id, 'source.sync', [
            'source_id' => 'source-rate-limit',
        ]);
        $admin           = $this->identity()->updateUser($userDto->id, $userDto->email, $userDto->displayName, ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $admin);
        $adminToken = $this->issueApiToken($admin, ['admin']);

        $this->fakeRateLimiter->queueResults(true, false);

        $testResponse = $this->postJson('/api/v1/admin/jobs/' . $jobDto->id . '/restart', [], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $adminToken,
        ]);

        $this->assertJsonRateLimited(
            $testResponse,
            'Too many API queue action requests. Please try again later.',
            60,
        );
        $this->assertLastRateLimitAttempt('api_queue_action', 30, 60);
    }

    #[Test]
    public function itRateLimitsPackageAndReleaseDeletesWithJsonResponse(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->organization()->registerOrganization('Delete Rate Limit Team', $userDto->id);
        $apiToken        = $this->issueApiToken($userDto, ['write']);
        $headers         = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiToken,
        ];

        $this->packages()->upsert(new PackageDto(
            name: 'limited/delete-demo',
            type: 'library',
            description: 'Rate limited delete package',
            visibility: 'private',
            sourceType: 'generic_git',
            sourceUrl: 'https://git.example.test/limited/delete-demo.git',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationDto->id,
            active: true,
            sourceId: '',
            downloadCount: 0,
        ));
        $this->releases()->publish(new ReleaseDto(
            packageName: 'limited/delete-demo',
            version: '1.0.0',
            reference: 'abc123',
            composer: [
                'name'    => 'limited/delete-demo',
                'version' => '1.0.0',
            ],
            source: [
                'type'      => 'git',
                'url'       => 'https://git.example.test/limited/delete-demo.git',
                'reference' => 'abc123',
            ],
            dist: [
                'type' => 'zip',
                'url'  => 'https://packio.us/dist/limited/delete-demo/1.0.0.zip',
            ],
            createdAt: '',
            organizationId: $organizationDto->id,
            active: true,
            downloadCount: 0,
        ));

        $this->fakeRateLimiter->queueResults(true, false);

        $testResponse = $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/packages/limited/delete-demo', [], $headers);

        $this->assertJsonRateLimited(
            $testResponse,
            'Too many destructive API requests. Please try again later.',
            60,
        );
        $this->assertLastRateLimitAttempt('api_destructive', 30, 60);

        $this->fakeRateLimiter->queueResults(true, false);

        $releaseDelete = $this->request('DELETE', '/api/v1/organizations/' . $organizationDto->slug . '/packages/limited/delete-demo/releases', [], $headers);

        $this->assertJsonRateLimited(
            $releaseDelete,
            'Too many destructive API requests. Please try again later.',
            60,
        );
        $this->assertLastRateLimitAttempt('api_destructive', 30, 60);
    }

    private function identity(): IdentityApiFacadeInterface
    {
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        return $identity;
    }

    private function audit(): AuditApiFacadeInterface
    {
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $audit);

        return $audit;
    }

    private function hasAuditEntry(string $organizationId, string $event, callable $matches, int $limit = 20): bool
    {
        return array_any(
            $this->audit()->recentByOrganization($organizationId, $limit),
            static fn ($auditEntryDto): bool => $event === $auditEntryDto->event && $matches($auditEntryDto),
        );
    }

    private function assertJsonRateLimited(TestResponse $testResponse, string $message, int $retryAfter): void
    {
        $testResponse
            ->assertStatus(429)
            ->assertHeader('Retry-After', (string) $retryAfter)
            ->assertJsonPath('error', $message)
            ->assertJsonPath('status', 429)
        ;
        self::assertStringStartsWith('application/json', $testResponse->header('Content-Type'));
    }

    private function assertLastRateLimitAttempt(string $policy, int $maxAttempts, int $decaySeconds): void
    {
        $lastKey = array_key_last($this->fakeRateLimiter->attempts);
        self::assertNotNull($lastKey);

        $attempt = $this->fakeRateLimiter->attempts[$lastKey];
        self::assertIsString($attempt['key']);
        self::assertStringStartsWith($policy . ':', $attempt['key']);
        self::assertSame($maxAttempts, $attempt['max_attempts']);
        self::assertSame($decaySeconds, $attempt['decay_seconds']);
    }

    private function assertLastRateLimitCheck(string $policy, int $maxAttempts, int $decaySeconds): void
    {
        $lastKey = array_key_last($this->fakeRateLimiter->checks);
        self::assertNotNull($lastKey);

        $check = $this->fakeRateLimiter->checks[$lastKey];
        self::assertIsString($check['key']);
        self::assertStringStartsWith($policy . ':', $check['key']);
        self::assertSame($maxAttempts, $check['max_attempts']);
        self::assertSame($decaySeconds, $check['decay_seconds']);
    }

    private function organization(): OrganizationApiFacadeInterface
    {
        $organization = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organization);

        return $organization;
    }

    private function packages(): PackageApiFacadeInterface
    {
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertInstanceOf(PackageApiFacadeInterface::class, $packages);

        return $packages;
    }

    private function releases(): ReleaseApiFacadeInterface
    {
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertInstanceOf(ReleaseApiFacadeInterface::class, $releases);

        return $releases;
    }

    private function jobs(): JobApiFacadeInterface
    {
        $jobs = $this->getService(JobApiFacadeInterface::class);
        self::assertInstanceOf(JobApiFacadeInterface::class, $jobs);

        return $jobs;
    }

    private function webhookEvents(): WebhookEventRepositoryInterface
    {
        $webhookEvents = $this->getService(WebhookEventRepositoryInterface::class);
        self::assertInstanceOf(WebhookEventRepositoryInterface::class, $webhookEvents);

        return $webhookEvents;
    }

    /**
     * @param list<string> $roles
     */
    private function createVerifiedUser(array $roles = ['user']): UserDto
    {
        return $this->createUser(verified: true, roles: $roles);
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function assertAdminOpenApiSectionHidden(array $openApi): void
    {
        $paths = $openApi['paths'] ?? [];
        self::assertIsArray($paths);

        foreach (array_keys($paths) as $path) {
            self::assertIsString($path);
            self::assertFalse(str_starts_with($path, '/api/v1/admin'), $path);
        }

        self::assertNotContains('Admin', array_column($openApi['tags'] ?? [], 'name'));

        $schemas = $openApi['components']['schemas'] ?? [];
        self::assertIsArray($schemas);
        self::assertArrayNotHasKey('AdminUserUpdateRequest', $schemas);
        self::assertArrayNotHasKey('AdminOrganizationUpdateRequest', $schemas);
        self::assertArrayNotHasKey('AdminSettingsUpdateRequest', $schemas);
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function assertAdminOpenApiSectionVisible(array $openApi): void
    {
        self::assertContains('Admin', array_column($openApi['tags'] ?? [], 'name'));
        self::assertArrayHasKey('/api/v1/admin/users', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/admin/organizations/{organizationId}', $openApi['paths'] ?? []);
        self::assertArrayHasKey('/api/v1/admin/jobs/{jobId}/restart', $openApi['paths'] ?? []);

        $schemas = $openApi['components']['schemas'] ?? [];
        self::assertIsArray($schemas);
        self::assertArrayHasKey('AdminUserUpdateRequest', $schemas);
        self::assertArrayHasKey('AdminOrganizationUpdateRequest', $schemas);
        self::assertArrayHasKey('AdminSettingsUpdateRequest', $schemas);
    }

    private function disablePublicApiDocs(): void
    {
        $projectSettings = $this->getService(ProjectSettingsApiFacadeInterface::class);
        self::assertInstanceOf(ProjectSettingsApiFacadeInterface::class, $projectSettings);

        $projectSettings->updateOverrides([
            ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC => RuntimeSettingOverride::Off->value,
        ]);
    }

    private function logIn(UserDto $userDto): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => 'Password1!',
        ])->assertRedirect('/organizations');
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(bool $verified, array $roles = ['user']): UserDto
    {
        $identity    = $this->identity();
        $userDto     = $identity->createUser(
            'api-' . bin2hex(random_bytes(6)) . '@example.test',
            'API User',
            'Password1!',
            $roles,
        );

        if ($verified) {
            $identity->markUserEmailAsVerified($userDto->id);
        }

        $updated = $identity->findUserById($userDto->id);
        self::assertInstanceOf(UserDto::class, $updated);

        return $updated;
    }

    /**
     * @param list<string> $scopes
     */
    private function issueApiToken(UserDto $userDto, array $scopes): string
    {
        return $this->identity()
            ->issueApiAccessTokenForUser($userDto->id, 'Test API token', $scopes)
            ->plainTextToken
        ;
    }

    /**
     * @param list<string> $scopes
     */
    private function rawIssueApiToken(UserDto $userDto, array $scopes): string
    {
        $commandService = $this->getService(ApiAccessTokenCommandService::class);
        self::assertInstanceOf(ApiAccessTokenCommandService::class, $commandService);

        return $commandService
            ->issueForUser($userDto->id, 'Raw test API token', $scopes)
            ->plainTextToken
        ;
    }
}
