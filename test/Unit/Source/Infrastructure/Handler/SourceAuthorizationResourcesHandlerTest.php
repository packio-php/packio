<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationResourcesHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function json_decode;
use function json_encode;

#[CoversClass(SourceAuthorizationResourcesHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceAuthorizationResourcesHandlerTest extends TestCase
{
    #[Test]
    public function authenticationFailureOnlyLooksUpAuthorizationInCurrentOrganization(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())->method('providerResources')->with('github', '1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e', 'org-1')->willThrowException(new ProviderAuthenticationException('private cause'));
        $sourceApiFacade->expects(self::once())->method('findProviderAuthorizationInOrganization')->with('1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e', 'org-1')->willReturn(null);
        $serverRequest = new ServerRequest()->withQueryParams([
            'provider' => 'github',
        ])
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'ours', 'Ours'))
            ->withAttribute('authorizationId', '1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e')
        ;
        $response = new SourceAuthorizationResourcesHandler($sourceApiFacade)->handle($serverRequest);
        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($body['authorization']);
        self::assertStringNotContainsString('private cause', (string) $response->getBody());
    }

    #[Test]
    public function returnsGenericErrorWhenProviderResourceLoadingFails(): void
    {
        $sourceApiFacade = $this->createMock(SourceApiFacadeInterface::class);
        $sourceApiFacade->expects(self::once())
            ->method('providerResources')
            ->with('bitbucket', '1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e', 'org-1')
            ->willThrowException(new RuntimeException('https://alice:password123@provider.test?token=secret-token'))
        ;

        $serverRequest = new ServerRequest([], [], '/organization/my-org/sources/authorizations/1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e/resources', 'GET')
            ->withQueryParams([
                'provider' => 'bitbucket',
            ])
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto('org-1', 'my-org', 'My Org'))
            ->withAttribute('authorizationId', '1c0b5d4f-2e3a-4b6c-9d7e-8f0a1b2c3d4e')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Provider resource loading failed.',
            self::callback(static function(array $context): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);
                self::assertStringNotContainsString('password123', $encoded);
                self::assertStringNotContainsString('secret-token', $encoded);
                self::assertSame('org-1', $context['organization_id']);
                self::assertNotEmpty($context['correlation_id']);

                return true;
            }),
        );
        $sourceAuthorizationResourcesHandler = new SourceAuthorizationResourcesHandler($sourceApiFacade, $logger);
        $response                            = $sourceAuthorizationResourcesHandler->handle($serverRequest);

        self::assertSame(500, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'Provider resources could not be loaded.',
            $body['error'] ?? null,
        );
    }
}
