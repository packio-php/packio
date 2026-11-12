<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Middleware;

use Cake\Chronos\Chronos;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use LogicException;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Packio\Application\Security\RateLimiter\RateLimiterInterface;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Identity\Application\Query\ApiAccessTokenQueryService;
use Packio\Identity\Application\Result\ApiAccessTokenResult;
use Packio\Identity\Application\Security\ApiAccessTokenHasher;
use Packio\Identity\Domain\Entity\ApiAccessToken as ApiAccessTokenEntity;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenStorage;
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenUsageRecordingActorProvider;
use Packio\Identity\Infrastructure\Authentication\DatabaseUserActorProvider;
use Packio\Identity\Infrastructure\Authentication\UserActor;
use Packio\Rest\Infrastructure\Authentication\ApiAccessTokenTransport;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Middleware\ApiAuthenticationContextMiddleware;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Fake\IdentityObjectMapper;
use PackioTest\Support\Fake\InMemoryRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\AuthenticationContext;
use Sirix\Mezzio\Authentication\Contract\ActorInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Authentication\Contract\TokenTransportInterface;
use Sirix\Mezzio\Authentication\Middleware\OptionalAuthenticateMiddleware;
use Sirix\Mezzio\Authentication\Token\AuthToken;
use Sirix\Mezzio\Authentication\TokenAuthenticator;
use Sirix\Mezzio\Authentication\TokenStorageProvider;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function get_object_vars;
use function is_array;
use function json_decode;

#[CoversClass(ApiAuthenticationContextMiddleware::class)]
final class ApiAuthenticationContextMiddlewareTest extends TestCase
{
    #[Test]
    public function missingCredentialPreservesTheAuthenticationRequiredResponse(): void
    {
        $apiAuthenticationContextCapturingHandler  = new ApiAuthenticationContextCapturingHandler();
        $response                                  = $this->middleware(null)->process(new ServerRequest(), $apiAuthenticationContextCapturingHandler);

        self::assertFalse($apiAuthenticationContextCapturingHandler->called);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('Authentication required.', $this->json($response)['error']['message'] ?? null);
    }

    #[Test]
    public function rejectedCredentialPreservesTheInvalidApiTokenResponse(): void
    {
        $apiAuthenticationContextCapturingHandler  = new ApiAuthenticationContextCapturingHandler();
        $response                                  = $this->middleware('present-but-invalid')->process(new ServerRequest(), $apiAuthenticationContextCapturingHandler);

        self::assertFalse($apiAuthenticationContextCapturingHandler->called);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Invalid API token.', $this->json($response)['error']['message'] ?? null);
        self::assertStringNotContainsString('present-but-invalid', (string) $response->getBody());
    }

    #[Test]
    public function rejectedCredentialRecordsOneFailure(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('attempt')
            ->with('api_authentication:ip:203.0.113.10', 60, 60)
        ;

        $response = $this->middleware('present-but-invalid', $this->attemptLimiter($rateLimiter))
            ->process(new ServerRequest(), new ApiAuthenticationContextCapturingHandler())
        ;

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function unverifiedActorReturnsTheExistingVerificationError(): void
    {
        $apiAuthenticationContextCapturingHandler  = new ApiAuthenticationContextCapturingHandler();
        $response                                  = $this->middlewareForContext()->process($this->requestWithContext($this->context(verified: false)), $apiAuthenticationContextCapturingHandler);

        self::assertFalse($apiAuthenticationContextCapturingHandler->called);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('email_verification_required', $this->json($response)['error']['code'] ?? null);
    }

    #[Test]
    public function bannedActorReturnsAccountBannedBeforeEmailVerification(): void
    {
        $apiAuthenticationContextCapturingHandler = new ApiAuthenticationContextCapturingHandler();
        $user                                     = $this->userDto(verified: false);
        $user                                     = new UserDto(...[
            ...get_object_vars($user),
            'isBanned'  => true,
            'banReason' => 'Violation of project rules.',
            'bannedAt'  => '2026-01-02T00:00:00+00:00',
        ]);

        $response = $this->middlewareForContext()->process($this->requestWithContext($this->context(userDto: $user)), $apiAuthenticationContextCapturingHandler);

        self::assertFalse($apiAuthenticationContextCapturingHandler->called);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('account_banned', $this->json($response)['error']['code'] ?? null);
        self::assertSame('Violation of project rules.', $this->json($response)['error']['details']['reason'] ?? null);
    }

    #[Test]
    public function itAttachesApiAndRequestContextAttributesFromTheProfileContext(): void
    {
        $apiAccessTokenDto                        = $this->tokenDto();
        $userDto                                  = $this->userDto();
        $apiAuthenticationContextCapturingHandler = new ApiAuthenticationContextCapturingHandler();

        $response = $this->middlewareForContext()
            ->process($this->requestWithContext($this->context(apiAccessTokenDto: $apiAccessTokenDto, userDto: $userDto)), $apiAuthenticationContextCapturingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($apiAuthenticationContextCapturingHandler->called);
        self::assertInstanceOf(ServerRequestInterface::class, $apiAuthenticationContextCapturingHandler->request);
        self::assertSame($apiAccessTokenDto, $apiAuthenticationContextCapturingHandler->request->getAttribute(ApiRequestAttributes::ACCESS_TOKEN));
        self::assertSame($userDto, $apiAuthenticationContextCapturingHandler->request->getAttribute(RequestContextAttributes::CURRENT_USER));
        self::assertSame($userDto->id, $apiAuthenticationContextCapturingHandler->request->getAttribute(RequestContextAttributes::CURRENT_USER_ID));
        self::assertSame($userDto, RequestContextAttributes::context($apiAuthenticationContextCapturingHandler->request)->currentUser);
    }

    #[Test]
    public function malformedAuthenticatedProfileContextFailsClosed(): void
    {
        $authenticationContext = new AuthenticationContext(
            new AuthToken('token-1', 'session', []),
            new UserActor('user-1', ['user'], $this->userDto()),
        );

        $this->expectException(LogicException::class);
        $this->middlewareForContext()->process($this->requestWithContext($authenticationContext), new ApiAuthenticationContextCapturingHandler());
    }

    #[Test]
    public function mismatchedApiTokenPayloadAndActorFailsClosed(): void
    {
        $apiAccessTokenDto     = $this->tokenDto();
        $authenticationContext = new AuthenticationContext(
            new AuthToken($apiAccessTokenDto->id, 'api_access_token', [
                'api_access_token' => $apiAccessTokenDto,
                'api_token_id'     => $apiAccessTokenDto->id,
                'user_id'          => 'different-user',
                'scopes'           => $apiAccessTokenDto->scopes,
            ]),
            new UserActor('different-user', ['user'], $this->userDto()),
        );

        $this->expectException(LogicException::class);
        $this->middlewareForContext()->process($this->requestWithContext($authenticationContext), new ApiAuthenticationContextCapturingHandler());
    }

    #[Test]
    public function malformedActorFailsClosed(): void
    {
        $apiAccessTokenDto     = $this->tokenDto();
        $authenticationContext = new AuthenticationContext(
            new AuthToken($apiAccessTokenDto->id, 'api_access_token', [
                'api_access_token' => $apiAccessTokenDto,
                'api_token_id'     => $apiAccessTokenDto->id,
                'user_id'          => $apiAccessTokenDto->userId,
                'scopes'           => $apiAccessTokenDto->scopes,
            ]),
            new class implements ActorInterface {
                public function getRoles(): array
                {
                    return ['user'];
                }
            },
        );

        $this->expectException(LogicException::class);
        $this->middlewareForContext()->process($this->requestWithContext($authenticationContext), new ApiAuthenticationContextCapturingHandler());
    }

    #[Test]
    public function mismatchedTokenIdFailsClosed(): void
    {
        $apiAccessTokenDto     = $this->tokenDto();
        $authenticationContext = new AuthenticationContext(
            new AuthToken('different-token', 'api_access_token', [
                'api_access_token' => $apiAccessTokenDto,
                'api_token_id'     => $apiAccessTokenDto->id,
                'user_id'          => $apiAccessTokenDto->userId,
                'scopes'           => $apiAccessTokenDto->scopes,
            ]),
            new UserActor($apiAccessTokenDto->userId, ['user'], $this->userDto()),
        );

        $this->expectException(LogicException::class);
        $this->middlewareForContext()->process($this->requestWithContext($authenticationContext), new ApiAuthenticationContextCapturingHandler());
    }

    #[Test]
    public function missingTokenOwnerIsRejectedByTheCompleteApiProfileFlowWithoutRecordingUse(): void
    {
        $apiAccessTokenEntity = ApiAccessTokenEntity::issue('missing-user', 'Deploy token', 'hash', 'packio_api_abcd', ['read']);
        $apiAccessTokenEntity->setIdentifier(Uuid::fromString('018f25e5-fd27-7000-8000-000000000002'));
        $apiAccessTokenEntity->setCreatedAt(new Chronos('2026-01-01T00:00:00+00:00'));

        $apiAccessTokenRepository = $this->createMock(ApiAccessTokenRepositoryInterface::class);
        $apiAccessTokenRepository->expects(self::once())->method('findByTokenHash')->willReturn($apiAccessTokenEntity);
        $apiAccessTokenRepository->expects(self::never())->method('findByIdForUser');
        $apiAccessTokenRepository->expects(self::never())->method('save');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findById')->with('missing-user')->willReturn(null);

        $objectMapper = $this->createStub(ObjectMapperInterface::class);
        $objectMapper->method('map')->willReturnCallback(
            static fn (object $source, string $target): object => ApiAccessTokenResult::class === $target
                ? new ApiAccessTokenResult(
                    id: '018f25e5-fd27-7000-8000-000000000002',
                    userId: 'missing-user',
                    name: 'Deploy token',
                    tokenPrefix: 'packio_api_abcd',
                    scopes: ['read'],
                    createdAt: '2026-01-01T00:00:00+00:00',
                )
                : new ApiAccessTokenDto(
                    id: '018f25e5-fd27-7000-8000-000000000002',
                    userId: 'missing-user',
                    name: 'Deploy token',
                    tokenPrefix: 'packio_api_abcd',
                    scopes: ['read'],
                    createdAt: '2026-01-01T00:00:00+00:00',
                ),
        );
        $apiAccessTokenQueryService = new ApiAccessTokenQueryService(
            $apiAccessTokenRepository,
            new ApiAccessTokenHasher('test-key'),
            $objectMapper,
        );
        $apiAccessTokenStorage                   = new ApiAccessTokenStorage($apiAccessTokenQueryService, $objectMapper);
        $apiAccessTokenTransport                 = new ApiAccessTokenTransport();
        $optionalAuthenticateMiddleware          = new OptionalAuthenticateMiddleware(
            new TokenAuthenticator(new ApiAccessTokenUsageRecordingActorProvider(
                new DatabaseUserActorProvider($userRepository, IdentityObjectMapper::create()),
                $apiAccessTokenQueryService,
            )),
            new TokenStorageProvider('api_access_token', [
                'api_access_token' => $apiAccessTokenStorage,
            ]),
            $apiAccessTokenTransport,
            'api_access_token',
        );
        $apiAuthenticationContextCapturingHandler = new ApiAuthenticationContextCapturingHandler();

        $response = $optionalAuthenticateMiddleware->process(
            new ServerRequest()->withHeader('Authorization', 'Bearer plain-token'),
            new ApiAuthenticationContextDelegatingHandler(
                $this->middlewareWithTransport($apiAccessTokenTransport),
                $apiAuthenticationContextCapturingHandler,
            ),
        );

        self::assertFalse($apiAuthenticationContextCapturingHandler->called);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Invalid API token.', $this->json($response)['error']['message'] ?? null);
        self::assertNull($apiAccessTokenEntity->lastUsedAt());
    }

    private function middleware(
        ?string $transportToken,
        ?ApiAuthenticationAttemptLimiter $apiAuthenticationAttemptLimiter = null
    ): ApiAuthenticationContextMiddleware {
        $transport = $this->createMock(TokenTransportInterface::class);
        $transport->expects(self::once())->method('fetch')->willReturn($transportToken);

        return $this->middlewareWithTransport($transport, $apiAuthenticationAttemptLimiter);
    }

    private function middlewareForContext(): ApiAuthenticationContextMiddleware
    {
        $provider = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::never())->method('get');

        return new ApiAuthenticationContextMiddleware($provider, new ApiErrorResponseFactory(), $this->attemptLimiter());
    }

    private function middlewareWithTransport(
        TokenTransportInterface $tokenTransport,
        ?ApiAuthenticationAttemptLimiter $apiAuthenticationAttemptLimiter = null,
    ): ApiAuthenticationContextMiddleware {
        $profile = $this->createMock(AuthenticationProfileInterface::class);
        $profile->expects(self::once())->method('transport')->willReturn($tokenTransport);
        $provider = $this->createMock(AuthenticationProfileProviderInterface::class);
        $provider->expects(self::once())->method('get')->with('api')->willReturn($profile);

        return new ApiAuthenticationContextMiddleware($provider, new ApiErrorResponseFactory(), $apiAuthenticationAttemptLimiter ?? $this->attemptLimiter());
    }

    private function attemptLimiter(?RateLimiterInterface $rateLimiter = null): ApiAuthenticationAttemptLimiter
    {
        $clientIpResolver = $this->createStub(ClientIpResolverInterface::class);
        $clientIpResolver->method('resolve')->willReturn('203.0.113.10');

        return new ApiAuthenticationAttemptLimiter(
            $rateLimiter ?? new InMemoryRateLimiter(),
            $clientIpResolver,
            [
                'api_authentication' => [
                    'max_attempts'  => 60,
                    'decay_seconds' => 60,
                ],
            ],
        );
    }

    private function context(
        ?ApiAccessTokenDto $apiAccessTokenDto = null,
        ?UserDto $userDto = null,
        bool $verified = true
    ): AuthenticationContext {
        $apiAccessTokenDto ??= $this->tokenDto();
        $userDto ??= $this->userDto(verified: $verified);

        return new AuthenticationContext(
            new AuthToken($apiAccessTokenDto->id, 'api_access_token', [
                'api_access_token' => $apiAccessTokenDto,
                'api_token_id'     => $apiAccessTokenDto->id,
                'user_id'          => $apiAccessTokenDto->userId,
                'scopes'           => $apiAccessTokenDto->scopes,
            ]),
            new UserActor($userDto->id, $userDto->roles, $userDto),
        );
    }

    private function requestWithContext(AuthenticationContext $authenticationContext): ServerRequest
    {
        return new ServerRequest()->withAttribute(AuthenticationAttributes::Context->value, $authenticationContext);
    }

    private function tokenDto(): ApiAccessTokenDto
    {
        return new ApiAccessTokenDto(
            id: 'token-1',
            userId: 'user-1',
            name: 'Test API token',
            tokenPrefix: 'packio_api_abcd',
            scopes: ['read'],
            createdAt: '2026-01-01T00:00:00+00:00',
        );
    }

    private function userDto(bool $verified = true): UserDto
    {
        return new UserDto(
            id: 'user-1',
            email: 'user@example.test',
            displayName: 'API User',
            roles: ['user'],
            createdAt: '2026-01-01T00:00:00+00:00',
            isEmailVerified: $verified,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true);

        return is_array($payload) ? $payload : [];
    }
}

final class ApiAuthenticationContextCapturingHandler implements RequestHandlerInterface
{
    public bool $called                     = false;
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called  = true;
        $this->request = $request;

        return new JsonResponse([
            'ok' => true,
        ]);
    }
}

final readonly class ApiAuthenticationContextDelegatingHandler implements RequestHandlerInterface
{
    public function __construct(
        private ApiAuthenticationContextMiddleware $apiAuthenticationContextMiddleware,
        private ApiAuthenticationContextCapturingHandler $apiAuthenticationContextCapturingHandler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->apiAuthenticationContextMiddleware->process($request, $this->apiAuthenticationContextCapturingHandler);
    }
}
