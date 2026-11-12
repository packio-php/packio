<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Resource;

use Packio\Rest\Infrastructure\Resource\SourceResource;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Source\Api\Dto\SourceDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

final class SourceResourceTest extends TestCase
{
    private const string FULL_JSON      = '{"id":"id-1","provider":"github","type":"repo","display_name":"Disp","canonical_url":"https:\/\/c\/x","url":"https:\/\/u\/x","owner":"own","repository":"rep","default_ref":"main","organization_id":"org-1","visibility":"private","active":false,"last_sync_status":"ok","last_successful_sync_at":"2026-01-01T00:00:00+00:00","last_failed_sync_at":"2026-01-02T00:00:00+00:00","last_sync_error":"err","archived":true,"archived_at":"2026-01-03T00:00:00+00:00","status":"archived","created_at":"2026-01-04T00:00:00+00:00","updated_at":"2026-01-05T00:00:00+00:00","provider_authorization_id":"pa-1","provider_resource_id":"pr-1","provider_config":{"branch":"main","n":1},"auth_type":"oauth","credentials_configured":true,"authorization_status":"active","keep_last_releases":7}';
    private const string EMPTY_JSON     = '{"id":"","provider":"","type":"","display_name":"","canonical_url":"","url":"","owner":"","repository":"","default_ref":"","organization_id":"","visibility":"","active":true,"last_sync_status":"","last_successful_sync_at":null,"last_failed_sync_at":null,"last_sync_error":null,"archived":false,"archived_at":null,"status":"","created_at":null,"updated_at":null,"provider_authorization_id":null,"provider_resource_id":null,"provider_config":{"webhook_secret":"s3cret","branch":"main"},"auth_type":null,"credentials_configured":false,"authorization_status":null,"keep_last_releases":0}';
    private const string NO_SECRET_JSON = '{"id":"","provider":"","type":"","display_name":"","canonical_url":"","url":"","owner":"","repository":"","default_ref":"","organization_id":"","visibility":"","active":true,"last_sync_status":"","last_successful_sync_at":null,"last_failed_sync_at":null,"last_sync_error":null,"archived":false,"archived_at":null,"status":"","created_at":null,"updated_at":null,"provider_authorization_id":null,"provider_resource_id":null,"provider_config":{"branch":"main"},"auth_type":null,"credentials_configured":false,"authorization_status":null,"keep_last_releases":0}';

    #[Test]
    #[DataProvider('nullableValues')]
    public function itPreservesTheSourceJsonContract(string $nullableValue, ?string $expectedNullable): void
    {
        $sourceDto = new SourceDto(
            id: 'id-value',
            provider: 'provider-value',
            type: 'type-value',
            displayName: 'displayName-value',
            canonicalUrl: 'canonicalUrl-value',
            url: 'url-value',
            owner: 'owner-value',
            repository: 'repository-value',
            defaultRef: 'defaultRef-value',
            organizationId: 'organizationId-value',
            visibility: 'visibility-value',
            active: false,
            lastSyncStatus: 'lastSyncStatus-value',
            lastSuccessfulSyncAt: $nullableValue,
            lastFailedSyncAt: $nullableValue,
            lastSyncError: $nullableValue,
            archived: false,
            archivedAt: $nullableValue,
            status: 'status-value',
            createdAt: $nullableValue,
            providerAuthorizationId: $nullableValue,
            providerResourceId: $nullableValue,
            providerConfig: [
                'branch' => 'main',
            ],
            authType: $nullableValue,
            credentialsConfigured: false,
            authorizationStatus: $nullableValue,
            keepLastReleases: 12,
            updatedAt: $nullableValue,
        );
        $sourceResource = SourceResource::fromDto($sourceDto);
        $response       = new ApiResponseFactory()->json([
            'data' => $sourceResource,
        ], 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame([
            'data' => [
                'id'                        => 'id-value',
                'provider'                  => 'provider-value',
                'type'                      => 'type-value',
                'display_name'              => 'displayName-value',
                'canonical_url'             => 'canonicalUrl-value',
                'url'                       => 'url-value',
                'owner'                     => 'owner-value',
                'repository'                => 'repository-value',
                'default_ref'               => 'defaultRef-value',
                'organization_id'           => 'organizationId-value',
                'visibility'                => 'visibility-value',
                'active'                    => false,
                'last_sync_status'          => 'lastSyncStatus-value',
                'last_successful_sync_at'   => $expectedNullable,
                'last_failed_sync_at'       => $expectedNullable,
                'last_sync_error'           => $expectedNullable,
                'archived'                  => false,
                'archived_at'               => $expectedNullable,
                'status'                    => 'status-value',
                'created_at'                => $expectedNullable,
                'updated_at'                => $expectedNullable,
                'provider_authorization_id' => $expectedNullable,
                'provider_resource_id'      => $expectedNullable,
                'provider_config'           => [
                    'branch' => 'main',
                ],
                'auth_type'                 => $expectedNullable,
                'credentials_configured'    => false,
                'authorization_status'      => $expectedNullable,
                'keep_last_releases'        => 12,
            ],
        ], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function nullableValues(): iterable
    {
        yield 'empty strings become null' => ['', null];

        yield 'nonempty strings are preserved' => ['2026-09-19T10:00:00+00:00', '2026-09-19T10:00:00+00:00'];

        yield 'zero is not empty' => ['0', '0'];
    }

    #[Test]
    public function fromDtoMatchesTheFormerObjectMapperJson(): void
    {
        self::assertSame(self::FULL_JSON, json_encode(SourceResource::fromDto($this->fullDto())));
        self::assertSame(self::EMPTY_JSON, json_encode(SourceResource::fromDto($this->emptyDto())));
    }

    #[Test]
    public function withoutWebhookSecretRemovesOnlyTheSecret(): void
    {
        self::assertSame(
            self::NO_SECRET_JSON,
            json_encode(SourceResource::withoutWebhookSecret(SourceResource::fromDto($this->emptyDto()))),
        );
    }

    private function fullDto(): SourceDto
    {
        return new SourceDto('id-1', 'github', 'repo', 'Disp', 'https://c/x', 'https://u/x', 'own', 'rep', 'main', 'org-1', 'private', false, 'ok', '2026-01-01T00:00:00+00:00', '2026-01-02T00:00:00+00:00', 'err', true, '2026-01-03T00:00:00+00:00', 'archived', '2026-01-04T00:00:00+00:00', 'pa-1', 'pr-1', [
            'branch' => 'main',
            'n'      => 1,
        ], 'oauth', true, 'active', 7, '2026-01-05T00:00:00+00:00');
    }

    private function emptyDto(): SourceDto
    {
        return new SourceDto('', '', '', '', '', '', '', '', '', '', '', true, '', '', '', '', false, '', '', '', '', '', [
            'webhook_secret' => 's3cret',
            'branch'         => 'main',
        ], '', false, '', 0, '');
    }
}
