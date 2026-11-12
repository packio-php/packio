<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function bin2hex;
use function random_bytes;
use function str_replace;

final class ApiRouteIdentifierFeatureTest extends FeatureTestCase
{
    #[Test]
    #[DataProvider('routes')]
    public function malformedAndMissingIdsAnswerTheSameNotFound(string $method, string $path, string $message, string $validCode): void
    {
        [$organization, $headers] = $this->adminFixture();

        foreach (['not-a-uuid', Uuid::uuid7()->toString()] as $id) {
            $uri  = str_replace(['{org}', '{id}'], [$organization->slug, $id], $path);
            $body = 'PATCH' === $method ? [
                'role' => 'member',
            ] : [];

            $testResponse = $this->request($method, $uri, $body, $headers, 'PATCH' === $method);
            if ('ok' === $validCode && 'not-a-uuid' !== $id) {
                $testResponse->assertStatus(200);

                continue;
            }

            $testResponse
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'ok' === $validCode ? 'not_found' : $validCode)
                ->assertJsonPath('error.message', $message)
            ;
        }
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function routes(): iterable
    {
        $org = '/api/v1/organizations/{org}';

        $routes = [
            ['DELETE', '/api/v1/user/api-tokens/{id}', 'API token not found.'],
            ['PUT', '/api/v1/user/api-tokens/{id}/regenerate', 'API token not found.'],
            ['POST', $org . '/invitations/{id}/revoke', 'Invitation not found.'],
            ['DELETE', $org . '/invitations/{id}', 'Invitation not found.'],
            ['PATCH', $org . '/members/{id}', 'Organization member not found.'],
            ['DELETE', $org . '/members/{id}', 'Organization member not found.'],
            ['DELETE', $org . '/registry-tokens/{id}', 'Registry token not found.'],
            ['PUT', $org . '/registry-tokens/{id}/regenerate', 'Registry token not found.'],
            ['GET', $org . '/sources/{id}', 'Source not found.'],
            ['GET', $org . '/sources/{id}/webhook-events', 'Source not found.'],
            ['GET', $org . '/provider-authorizations/{id}/dependencies', 'Provider authorization not found.', 'ok'],
            ['DELETE', $org . '/provider-authorizations/{id}', 'Provider authorization not found.'],
            ['DELETE', $org . '/source-provider-instances/{id}', 'Source provider instance was not found.', 'source_provider_instance_not_found'],
            ['POST', $org . '/source-provider-instances/{id}/activate', 'Source provider instance was not found.', 'source_provider_instance_not_found'],
            ['GET', '/api/v1/admin/jobs/{id}', 'Job not found.'],
            ['POST', '/api/v1/admin/jobs/{id}/restart', 'Job not found.'],
            ['GET', '/api/v1/admin/organizations/{id}', 'Organization not found.'],
            ['GET', '/api/v1/admin/users/{id}', 'User not found.'],
            ['POST', '/api/v1/admin/users/{id}/verify', 'User not found.'],
        ];

        // 4th item: error code for a valid-but-missing id, kept as today; 'ok' means today's 200 response.
        foreach ($routes as $route) {
            yield $route[0] . ' ' . $route[1] => [$route[0], $route[1], $route[2], $route[3] ?? 'not_found'];
        }
    }

    #[Test]
    public function urnFormattedUuidReturnsNotFound(): void
    {
        [, $headers] = $this->adminFixture();

        $this->get('/api/v1/admin/users/urn:uuid:' . Uuid::uuid7()->toString(), $headers)
            ->assertStatus(404)
            ->assertJsonPath('error.message', 'User not found.')
        ;
    }

    #[Test]
    public function unknownOrganizationSlugReturnsNotFound(): void
    {
        [$organization, $headers] = $this->adminFixture();
        $missing                  = 'no-such-org-' . bin2hex(random_bytes(4));

        foreach ([$missing, Uuid::uuid7()->toString()] as $segment) {
            foreach (['', '/sources'] as $suffix) {
                $this->get('/api/v1/organizations/' . $segment . $suffix, $headers)
                    ->assertStatus(404)
                    ->assertJsonPath('error.message', 'Organization not found.')
                ;
            }
        }

        $this->get('/api/v1/organizations/' . $organization->id, $headers)->assertStatus(200);
    }

    #[Test]
    public function webhookReprocessKeepsProviderDeliveryIds(): void
    {
        [$organization, $headers] = $this->adminFixture();

        $testResponse = $this->postJson('/api/v1/organizations/' . $organization->slug . '/sources', [
            'provider'   => 'generic_git',
            'resource'   => 'https://git.example.test/acme/reprocess-' . bin2hex(random_bytes(4)) . '.git',
            'visibility' => 'private',
        ], $headers);
        $testResponse->assertStatus(201);

        $this->request(
            'POST',
            '/api/v1/organizations/' . $organization->slug . '/sources/' . $testResponse->json()['data']['id'] . '/webhook-events/gh-delivery-1/reprocess',
            [],
            $headers,
        )
            ->assertStatus(404)
            ->assertJsonPath('error.message', 'Webhook delivery not found.')
        ;
    }

    /** @return array{OrganizationDto, array<string, string>} */
    private function adminFixture(): array
    {
        $identity     = $this->getService(IdentityApiFacadeInterface::class);
        $organization = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organization);

        $email    = 'admin-' . bin2hex(random_bytes(6)) . '@example.test';
        $userDto  = $identity->createUser($email, 'Route Admin', 'Password1!');
        $identity->markUserEmailAsVerified($userDto->id);
        $identity->updateUser($userDto->id, $email, 'Route Admin', ['user', 'admin']);
        self::assertInstanceOf(UserDto::class, $identity->findUserById($userDto->id));

        $token = $identity->issueApiAccessTokenForUser($userDto->id, 'Route API token', ['admin', 'write'])->plainTextToken;

        return [
            $organization->registerOrganization('Route Org ' . bin2hex(random_bytes(4)), $userDto->id),
            [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ];
    }
}
