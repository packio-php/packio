<?php

declare(strict_types=1);

namespace PackioTest\Feature\Application;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;
use function str_replace;

final class WebRouteIdentifierFeatureTest extends FeatureTestCase
{
    private const string PASSWORD       = 'Password1!';
    private const string MISSING_ID     = '0b9a4c3e-1d2f-4a5b-8c6d-7e8f9a0b1c2d';
    private const string URN_MISSING_ID = 'urn:uuid:' . self::MISSING_ID;
    private const string INVALID_ID     = 'not-a-uuid';

    #[Test]
    public function anonymousWebhookWithInvalidSourceIdAnswersLikeMissingSource(): void
    {
        $testResponse = $this->post('/webhooks/github/' . self::INVALID_ID);
        $missing      = $this->post('/webhooks/github/' . self::MISSING_ID);

        self::assertNotSame(500, $testResponse->status());
        self::assertSame($missing->status(), $testResponse->status());
    }

    #[Test]
    public function invalidIdsAnswerLikeMissingIds(): void
    {
        $slug = $this->logInAsAdminOwner()->slug;

        foreach ($this->routes($slug) as $route) {
            [$method, $path] = $route;

            $invalid = $this->send($method, str_replace('{id}', self::INVALID_ID, $path));
            $missing = $this->send($method, str_replace('{id}', self::MISSING_ID, $path));

            self::assertNotSame(500, $invalid->status(), $method . ' ' . $path);
            self::assertSame($missing->status(), $invalid->status(), $method . ' ' . $path);
            self::assertSame(
                str_replace(self::MISSING_ID, self::INVALID_ID, $missing->header('Location')),
                $invalid->header('Location'),
                $method . ' ' . $path,
            );
        }
    }

    #[Test]
    public function invalidAuthorizationIdsAnswerNotFound(): void
    {
        $slug = $this->logInAsAdminOwner()->slug;

        foreach ($this->authorizationRoutes($slug) as [$method, $path]) {
            self::assertSame(404, $this->send($method, str_replace('{id}', self::INVALID_ID, $path))->status(), $method . ' ' . $path);
        }
    }

    #[Test]
    public function urnIdsAnswerLikeMissingIds(): void
    {
        $slug = $this->logInAsAdminOwner()->slug;

        foreach ([...$this->routes($slug), ...$this->authorizationRoutes($slug)] as $route) {
            [$method, $path] = $route;

            $urn     = $this->send($method, str_replace('{id}', self::URN_MISSING_ID, $path));
            $missing = $this->send($method, str_replace('{id}', self::MISSING_ID, $path));

            self::assertNotSame(500, $urn->status(), $method . ' ' . $path);
            self::assertSame($missing->status(), $urn->status(), $method . ' ' . $path);
            self::assertSame(
                str_replace(self::MISSING_ID, self::URN_MISSING_ID, $missing->header('Location')),
                $urn->header('Location'),
                $method . ' ' . $path,
            );
        }
    }

    #[Test]
    public function reprocessWithExistingSourceAndProviderDeliveryIdKeepsItsAnswer(): void
    {
        $organizationDto = $this->logInAsAdminOwner();
        $slug            = $organizationDto->slug;

        /** @var SourceApiFacadeInterface $sources */
        $sources   = $this->getService(SourceApiFacadeInterface::class);
        $sourceDto = $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/org/reprocess-' . bin2hex(random_bytes(6)),
            $organizationDto->id,
        ));

        $testResponse = $this->send('POST', '/organization/' . $slug . '/sources/webhooks/' . $sourceDto->id . '/x/reprocess');
        self::assertSame(302, $testResponse->status());
        self::assertSame('/organization/' . $slug . '/sources/' . $sourceDto->id, $testResponse->header('Location'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function routes(string $slug): array
    {
        $organization = '/organization/' . $slug;

        return [
            ['GET', '/admin/users/{id}'],
            ['POST', '/admin/users/{id}'],
            ['POST', '/admin/users/{id}/ban'],
            ['POST', '/admin/users/{id}/unban'],
            ['POST', '/admin/users/{id}/verify'],
            ['GET', '/admin/organizations/{id}'],
            ['POST', '/admin/organizations/{id}'],
            ['POST', '/admin/organizations/{id}/delete'],
            ['POST', '/admin/organizations/{id}/invitations'],
            ['POST', '/admin/organizations/{id}/invitations/{id}/revoke'],
            ['POST', '/admin/organizations/{id}/invitations/{id}/delete'],
            ['GET', '/admin/jobs/{id}'],
            ['POST', '/admin/jobs/{id}/restart'],
            ['GET', '/admin/sources/{id}/deletion-preview'],
            ['POST', '/admin/sources/{id}/delete'],
            ['POST', '/admin/sources/{id}/requeue-delete'],
            ['POST', '/admin/sources/{id}/cancel-delete'],
            ['POST', '/admin/sources/{id}/force-requeue-delete'],
            ['POST', '/admin/sources/{id}/force-finalize-delete'],
            ['GET', $organization . '/sources/{id}'],
            ['GET', $organization . '/source-provider-instances/{id}/dependencies'],
            ['POST', $organization . '/source-provider-instances/{id}/delete-inactive-authorizations'],
            ['POST', $organization . '/sources/{id}/settings'],
            ['POST', $organization . '/sources/{id}/resync'],
            ['POST', $organization . '/sources/{id}/webhook/register'],
            ['POST', $organization . '/sources/{id}/webhook/rotate'],
            ['POST', $organization . '/sources/{id}/packages/activity'],
            ['POST', $organization . '/sources/webhooks/{id}/x/reprocess'],
            ['POST', $organization . '/invitations/{id}/delete'],
            ['POST', $organization . '/invitations/{id}/revoke'],
            ['POST', $organization . '/members/{id}/remove'],
            ['POST', $organization . '/members/{id}/role'],
            ['POST', $organization . '/tokens/{id}/revoke'],
            ['POST', '/profile/oauth/{id}/disconnect'],
            ['POST', '/profile/api-tokens/{id}/revoke'],
            ['DELETE', '/profile/api-tokens/{id}'],
        ];
    }

    /**
     * A non-UUID authorization id answers 404 (it used to be a 500), while a missing one gets the facade's 200 payload; URN must match it.
     *
     * @return list<array{string, string}>
     */
    private function authorizationRoutes(string $slug): array
    {
        $organization = '/organization/' . $slug;

        return [
            ['GET', $organization . '/sources/authorizations/{id}/resources?provider=github'],
            ['POST', $organization . '/sources/authorizations/{id}/check'],
        ];
    }

    private function send(string $method, string $uri): TestResponse
    {
        $headers = [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ];

        if ('GET' === $method) {
            return $this->get($uri, $headers);
        }

        return $this->withCsrf()->request($method, $uri, [], $headers);
    }

    private function logInAsAdminOwner(): OrganizationDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'route-ids-' . bin2hex(random_bytes(6)) . '@example.test',
            'Route Ids User',
            self::PASSWORD,
            ['user', 'admin'],
        );
        $identity->markUserEmailAsVerified($userDto->id);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization('route-ids-' . bin2hex(random_bytes(6)), $userDto->id);

        $this->withCsrf()->post('/auth/login', [
            'email'    => $userDto->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');

        return $organizationDto;
    }
}
