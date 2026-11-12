<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Handler;

use Common\Branding\BrandingConfig;
use LogicException;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\Handler\RegistrySetupHandler;
use Packio\Registry\Infrastructure\Page\RegistrySetupPageDataBuilder;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionClass;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;

use function in_array;

#[CoversClass(RegistrySetupHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class RegistrySetupHandlerTest extends TestCase
{
    private RegistrySetupPageDataBuilder $registrySetupPageDataBuilder;

    /** @var MockObject&RegistryApiFacadeInterface */
    private RegistryApiFacadeInterface $registryApiFacade;

    /** @var InertiaInterface&MockObject */
    private InertiaInterface $inertia;

    /** @var MockObject&ServerRequestInterface */
    private ServerRequestInterface $serverRequest;

    private RegistrySetupHandler $registrySetupHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registryApiFacade = $this->createMock(RegistryApiFacadeInterface::class);

        $brandingConfig = new BrandingConfig([
            'domain'                    => 'packio.us',
            'registry_base_domain'      => 'packio.us',
            'app_url'                   => 'https://app.packio.us',
            'composer_repository_alias' => 'packio',
            'short_name'                => 'packio',
            'product_name'              => 'Packio',
        ]);

        $this->registrySetupPageDataBuilder = new RegistrySetupPageDataBuilder(
            $this->registryApiFacade,
            $brandingConfig,
        );

        $this->inertia       = $this->createMock(InertiaInterface::class);
        $this->serverRequest = $this->createMock(ServerRequestInterface::class);

        $this->registrySetupHandler = new RegistrySetupHandler($this->registrySetupPageDataBuilder);
    }

    public function testHandleReturnsInertiaPageWithBuilderData(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
            anonymousAccess: true,
        );

        $userDto = new UserDto(
            id: 'user-01',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2024-01-01T00:00:00+00:00',
        );

        $this->serverRequest
            ->method('getAttribute')
            ->willReturnMap([
                [InertiaMiddleware::INERTIA_ATTRIBUTE, $this->inertia],
                [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
                [RequestContextAttributes::CURRENT_USER, $userDto],
            ])
        ;

        $this->registryApiFacade
            ->method('packageOverview')
            ->with('org-01')
            ->willReturn([])
        ;

        $response = $this->createMock(ResponseInterface::class);

        $this->inertia
            ->expects(self::once())
            ->method('render')
            ->with(
                'Registry/Setup',
                self::callback(function(array $props) use ($organizationDto): bool {
                    self::assertSame('acme.packio.us', $props['registry_host']);
                    self::assertSame('https://acme.packio.us/packages.json', $props['public_bootstrap']);
                    self::assertSame('https://acme.packio.us/private/packages.json', $props['private_bootstrap']);
                    self::assertArrayNotHasKey('composer_repository_alias', $props);
                    self::assertSame($organizationDto->id, $props['organization']['id']);
                    self::assertTrue($props['organization']['anonymous_access']);

                    return true;
                }),
            )
            ->willReturn($response)
        ;

        $result = $this->registrySetupHandler->handle($this->serverRequest);

        self::assertSame($response, $result);
    }

    public function testHandleRequiresResolvedOrganizationContext(): void
    {
        $this->serverRequest
            ->method('getAttribute')
            ->willReturnMap([
                [InertiaMiddleware::INERTIA_ATTRIBUTE, $this->inertia],
                [RequestContextAttributes::CURRENT_ORGANIZATION, null],
            ])
        ;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Current organization is not resolved.');

        $this->registrySetupHandler->handle($this->serverRequest);
    }

    public function testHandlePassesNullUserToBuilderWhenAttributeNotUserDto(): void
    {
        $organizationDto = new OrganizationDto(
            id: 'org-01',
            slug: 'acme',
            name: 'Acme Corp',
        );

        $this->serverRequest
            ->method('getAttribute')
            ->willReturnMap([
                [InertiaMiddleware::INERTIA_ATTRIBUTE, $this->inertia],
                [RequestContextAttributes::CURRENT_ORGANIZATION, $organizationDto],
                [RequestContextAttributes::CURRENT_USER, 'not-a-user-object'],
            ])
        ;

        $this->registryApiFacade
            ->method('packageOverview')
            ->with('org-01')
            ->willReturn([])
        ;

        $response = $this->createMock(ResponseInterface::class);

        $this->inertia
            ->expects(self::once())
            ->method('render')
            ->with(
                'Registry/Setup',
                self::callback(function(array $props): bool {
                    // Anonymous user => no private bootstrap, no private packages
                    self::assertNull($props['private_bootstrap']);
                    self::assertCount(0, $props['private_packages']);

                    return true;
                }),
            )
            ->willReturn($response)
        ;

        $result = $this->registrySetupHandler->handle($this->serverRequest);

        self::assertSame($response, $result);
    }

    public function testRouteHasRequireExistingOrganizationMiddleware(): void
    {
        $attributes = $this->getRouteAttributes();

        $hasMiddleware = false;

        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (
                isset($args['middleware'])
                && in_array(RequireExistingOrganizationMiddleware::class, $args['middleware'], true)
            ) {
                $hasMiddleware = true;

                break;
            }
        }

        self::assertTrue($hasMiddleware, 'Route attribute must include RequireExistingOrganizationMiddleware');
    }

    public function testRouteIsAuthenticated(): void
    {
        $attributes = $this->getRouteAttributes();

        $hasAuthenticated = false;

        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            if (Authenticated::class === $name) {
                $hasAuthenticated = true;

                break;
            }
        }

        self::assertTrue($hasAuthenticated, 'Handler must be marked with #[Authenticated]');
    }

    /**
     * @return list<ReflectionAttribute<object>>
     */
    private function getRouteAttributes(): array
    {
        $reflectionClass = new ReflectionClass(RegistrySetupHandler::class);

        return $reflectionClass->getAttributes();
    }
}
