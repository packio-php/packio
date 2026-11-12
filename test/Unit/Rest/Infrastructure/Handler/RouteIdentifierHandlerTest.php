<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokenRegenerateHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensDeleteHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensDeleteHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensRegenerateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceActivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeactivateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceDeleteHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

use function json_decode;
use function str_contains;

final class RouteIdentifierHandlerTest extends TestCase
{
    /** @param class-string<RequestHandlerInterface> $class */
    #[DataProvider('invalidIdentifiers')]
    public function testInvalidIdentifiersReturnNotFoundWithoutBusinessCalls(string $class, bool $source, mixed $input): void
    {
        $facade = $this->createMock($source ? SourceApiFacadeInterface::class : IdentityApiFacadeInterface::class);
        $facade->expects(self::never())->method(self::anything());
        $handler = new $class($facade, new ApiResponseFactory(), new ApiErrorResponseFactory());
        $request = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER, new UserDto('user', 'user@example.test', 'User', [], ''))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org', 'user', 'owner'))
        ;
        if (null !== $input) {
            $request = $request->withAttribute($source ? 'instanceId' : 'tokenId', $input);
        }
        $response = $handler->handle($request);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame($source ? 'source_provider_instance_not_found' : 'not_found', json_decode((string) $response->getBody(), true)['error']['code']);
    }

    /** @return iterable<array{class-string<RequestHandlerInterface>, bool, mixed}> */
    public static function invalidIdentifiers(): iterable
    {
        foreach ([ApiAccessTokensDeleteHandler::class, ApiAccessTokenRegenerateHandler::class,
            RegistryTokensDeleteHandler::class, RegistryTokensRegenerateHandler::class,
            SourceProviderInstanceActivateHandler::class, SourceProviderInstanceDeactivateHandler::class,
            SourceProviderInstanceDeleteHandler::class, SourceProviderInstanceUpdateHandler::class] as $class) {
            foreach ([null, false, 123, [], " \t "] as $input) {
                yield [$class, str_contains($class, 'SourceProvider'), $input];
            }
        }
    }
}
