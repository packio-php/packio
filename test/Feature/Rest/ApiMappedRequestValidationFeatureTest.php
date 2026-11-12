<?php

declare(strict_types=1);

namespace PackioTest\Feature\Rest;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\OrganizationsCreateHandler;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function bin2hex;
use function random_bytes;
use function str_starts_with;

final class SpyRateLimiter implements RateLimiterInterface
{
    /** @var list<string> */
    public array $attemptedKeys = [];

    public function __construct(private readonly RateLimiterInterface $rateLimiter) {}

    public function isExceeded(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->rateLimiter->isExceeded($key, $maxAttempts, $decaySeconds);
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->attemptedKeys[] = $key;

        return $this->rateLimiter->attempt($key, $maxAttempts, $decaySeconds);
    }

    public function clear(string $key): void
    {
        $this->rateLimiter->clear($key);
    }
}

final class HandlerCallSpy implements RequestHandlerInterface
{
    public int $invocations = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->invocations;

        return new JsonResponse([
            'error' => [
                'code'    => 'spy_handler_reached',
                'message' => 'Spy.',
            ],
        ], 500);
    }
}

final class ApiMappedRequestValidationFeatureTest extends FeatureTestCase
{
    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'api-validation-' . bin2hex(random_bytes(6)) . '@example.test',
            'API Validation User',
            'Password1!',
            ['user'],
        );
        $identity->markUserEmailAsVerified($userDto->id);
        $this->apiToken = $identity
            ->issueApiAccessTokenForUser($userDto->id, 'API validation token', ['organizations:write'])
            ->plainTextToken
        ;
    }

    #[Test]
    public function itRejectsASemanticallyInvalidMappedBodyWithTheApiEnvelopeWithoutCallingTheHandlerMutation(): void
    {
        $this->serviceOverrides[RateLimiterInterface::class]
                                                                   = new SpyRateLimiter($this->getService(RateLimiterInterface::class));
        $this->serviceOverrides[OrganizationsCreateHandler::class] = new HandlerCallSpy();
        $this->rebuildContainer();

        $this->rebuildApplication();

        $testResponse = $this->postJson('/api/v1/organizations', [
            'name' => '  ',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken,
        ]);

        $testResponse
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'Invalid request data.')
            ->assertJsonPath('error.details.name.0', 'Name is required.')
        ;

        $handlerSpy = $this->getService(OrganizationsCreateHandler::class);
        self::assertInstanceOf(HandlerCallSpy::class, $handlerSpy);
        self::assertSame(0, $handlerSpy->invocations);

        $rateLimiter = $this->getService(RateLimiterInterface::class);
        self::assertInstanceOf(SpyRateLimiter::class, $rateLimiter);
        self::assertNotSame([], $rateLimiter->attemptedKeys);
        self::assertTrue((bool) array_filter(
            $rateLimiter->attemptedKeys,
            static fn (string $key): bool => str_starts_with($key, 'api_write:'),
        ));
    }

    #[Test]
    public function itCreatesTheOrganizationWhenTheMappedBodyIsValid(): void
    {
        $this->postJson('/api/v1/organizations', [
            'name' => 'Valid Organization',
        ], [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken,
        ])->assertStatus(201);
    }

    #[Test]
    public function itReturnsTheApiEnvelopeEvenWithAnInertiaHeader(): void
    {
        $testResponse = $this->postJson('/api/v1/organizations', [
            'name' => '  ',
        ], [
            'Accept'           => 'application/json',
            'Authorization'    => 'Bearer ' . $this->apiToken,
            'X-Inertia'        => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $testResponse
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
        ;
    }
}
