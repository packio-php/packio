<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Common\Exception\PublicValidationException;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourcesCreateHandler;
use Packio\Rest\Infrastructure\Request\CreateApiAccessTokenRequest;
use Packio\Rest\Infrastructure\Request\CreateProviderAuthorizationRequest;
use Packio\Rest\Infrastructure\Request\CreateSourceRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Response\ApiResponseFactory;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Exception\SourceInputException;
use Packio\Source\Api\SourceApiFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class CreateErrorHandlerTest extends TestCase
{
    #[DataProvider('validationModes')]
    public function testTokenCreationDistinguishesValidationFromInfrastructureFailure(bool $validation): void
    {
        $facade = $this->createMock(IdentityApiFacadeInterface::class);
        $facade->expects(self::once())->method('issueApiAccessTokenForUser')->willThrowException(
            $validation ? new PublicValidationException('Expiration date is invalid.') : new RuntimeException('token=private-secret'),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($validation ? self::never() : self::once())->method('error');
        $apiAccessTokensCreateHandler = new ApiAccessTokensCreateHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $logger);
        $serverRequest                = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_USER, new UserDto('user-1', 'user@example.test', 'User', [], ''))
            ->withAttribute(CreateApiAccessTokenRequest::class, new CreateApiAccessTokenRequest([
                'name' => 'Token',
            ]))
        ;
        $response = $apiAccessTokensCreateHandler->handle($serverRequest);
        self::assertSame($validation ? 422 : 500, $response->getStatusCode());
        self::assertStringNotContainsString('private-secret', (string) $response->getBody());
        self::assertStringContainsString($validation ? 'Expiration date is invalid.' : 'internal_error', (string) $response->getBody());
    }

    /** @return iterable<string, array{bool}> */
    public static function validationModes(): iterable
    {
        yield 'public validation' => [true];

        yield 'unknown runtime' => [false];
    }

    #[DataProvider('sourceModes')]
    public function testSourceCreationAndAuthorizationDoNotExposeUnknownRuntimeFailures(bool $authorization, bool $validation): void
    {
        $facade = $this->createMock(SourceApiFacadeInterface::class);
        $facade->expects(self::once())->method($authorization ? 'authorizeProviderInContext' : 'registerSourceInContext')->willThrowException(
            $validation ? new SourceInputException('Source provider instance is not available.') : new RuntimeException('token=private-secret'),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($validation ? self::never() : self::once())->method('error');
        $handler = $authorization
            ? new ProviderAuthorizationsCreateHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $logger)
            : new SourcesCreateHandler($facade, new ApiResponseFactory(), new ApiErrorResponseFactory(), $logger);
        $serverRequest = new ServerRequest()
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'org', 'Organization'))
            ->withAttribute(RequestContextAttributes::ORGANIZATION_ACTOR_CONTEXT, new ActorContext('org-1', 'user-1', 'owner'))
            ->withAttribute(CreateProviderAuthorizationRequest::class, new CreateProviderAuthorizationRequest([
                'provider' => 'github',
                'payload'  => [],
            ]))
            ->withAttribute(CreateSourceRequest::class, new CreateSourceRequest([
                'provider' => 'github',
                'resource' => 'https://github.com/org/repo',
                'settings' => [],
            ]))
        ;
        $response = $handler->handle($serverRequest);
        self::assertSame($validation ? 422 : 500, $response->getStatusCode());
        self::assertStringNotContainsString('private-secret', (string) $response->getBody());
        self::assertStringContainsString($validation ? 'Source provider instance is not available.' : 'internal_error', (string) $response->getBody());
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function sourceModes(): iterable
    {
        yield 'source public validation' => [false, true];

        yield 'source unknown runtime' => [false, false];

        yield 'authorization public validation' => [true, true];

        yield 'authorization unknown runtime' => [true, false];
    }
}
