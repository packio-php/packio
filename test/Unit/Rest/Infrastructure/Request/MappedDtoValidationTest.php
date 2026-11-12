<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Request;

use Common\Registry\RegistryProtocolPermissions;
use CuyZ\Valinor\MapperBuilder;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Rest\Infrastructure\Handler;
use Packio\Rest\Infrastructure\Request;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Validation\ApiValidationFailureResponseFactory;
use PackioTest\Support\Fake\ValidationFailureCollaborators;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Yiisoft\Validator\Validator;

use function array_keys;
use function array_replace;
use function json_decode;

final class MappedDtoValidationTest extends TestCase
{
    use ValidationFailureCollaborators;

    /**
     * @param class-string                $handlerClass
     * @param array<string, mixed>        $payload
     * @param array<string, list<string>> $details
     * @param array<string, mixed>        $query
     */
    #[DataProvider('requests')]
    public function testMappedDtoUsesRealRouteRulesBeforeCallingTheHandler(
        string $handlerClass,
        array $payload,
        array $details,
        array $query = []
    ): void {
        $reflectionClass       = new ReflectionClass($handlerClass);
        $mapRequest            = $reflectionClass->getAttributes(MapRequest::class)[0]->newInstance();
        $validateMappedRequest = $reflectionClass->getAttributes(ValidateMappedRequest::class)[0]->newInstance();
        $dto                   = $mapRequest->source;
        self::assertNotNull($dto);
        $input = [
            'payload' => $payload,
        ];
        if ([] !== $query) {
            $input['query'] = $query;
        }
        $form    = new MapperBuilder()->mapper()->map($dto, $input);
        $handler = $this->createMock(RequestHandlerInterface::class);
        if ([] === $details) {
            $handler->expects(self::once())->method('handle')->willReturn(new EmptyResponse());
        } else {
            $handler->expects(self::never())->method('handle');
        }
        $route = new Route('/api/v1/test', $this->createStub(MiddlewareInterface::class), ['POST']);
        $route->setOptions($validateMappedRequest->mergeDefaults($mapRequest->mergeDefaults([])));
        $serverRequest = new ServerRequest(uri: '/api/v1/test', method: 'POST')
            ->withAttribute($dto, $form)
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route))
        ;
        $validateMappedRequestMiddleware = self::validateMappedRequestMiddleware(
            new Validator(),
            self::validationFailureResponseDispatcher(validationFailureResponseFactory: new ApiValidationFailureResponseFactory(new ApiErrorResponseFactory())),
        );
        $response = $validateMappedRequestMiddleware->process($serverRequest, $handler);
        self::assertSame([] === $details ? 204 : 422, $response->getStatusCode());
        if ([] !== $details) {
            self::assertSame([
                'error' => [
                    'code'    => 'validation_failed',
                    'message' => 'Invalid request data.',
                    'details' => $details,
                ],
            ], json_decode((string) $response->getBody(), true));
        }
    }

    /** @return iterable<array{class-string, array<string, mixed>, array<string, list<string>>, 3?: array<string, mixed>}> */
    public static function requests(): iterable
    {
        $source = [
            'provider' => 'github',
            'resource' => 'repo',
        ];

        yield [Handler\SourcesCreateHandler::class, $source, []];

        yield [Handler\SourcesCreateHandler::class, $source + [
            'settings' => [],
        ], []];

        yield [Handler\SourcesCreateHandler::class, $source + [
            'keepLastReleases' => '0',
        ], []];
        foreach ([null, [], false, '', 'secret'] as $value) {
            yield [
                Handler\SourcesCreateHandler::class, $source + [
                    'visibility' => $value,
                ], [
                    'visibility' => ['Visibility must be public or private.'],
                ]];
        }

        foreach ([-1, 1.5, null, true, [], '01', '1.5'] as $value) {
            yield [
                Handler\SourcesCreateHandler::class, $source + [
                    'keep_last_releases' => $value,
                ], [
                    'keep_last_releases' => ['Keep last releases must be a non-negative integer.'],
                ]];
        }

        yield [Handler\SourcesCreateHandler::class, $source + [
            'keep_last_releases' => 0,
            'keepLastReleases'   => -1,
        ], []];

        yield [Handler\SourceUpdateHandler::class, [], []];

        yield [Handler\SourceUpdateHandler::class, [
            'active'           => false,
            'keepLastReleases' => '0',
        ], []];

        yield [
            Handler\SourceUpdateHandler::class, [
                'active' => null,
            ], [
                'active' => ['Active must be a boolean.'],
            ]];

        yield [
            Handler\SourceUpdateHandler::class, [
                'visibility' => null,
            ], [
                'visibility' => ['Visibility must be public or private.'],
            ]];

        yield [
            Handler\ReleaseActivityHandler::class, [
                'active' => true,
            ], [
                'version' => ['Version is required.'],
            ]];

        yield [
            Handler\ReleaseActivityHandler::class, [
                'active' => 'invalid',
            ], [
                'version' => ['Version is required.'],
            ]];

        yield [Handler\ReleaseActivityHandler::class, [
            'active' => 'invalid',
        ], [
            'active' => ['Active must be a boolean.'],
        ], [
            'version' => 'v1',
        ]];

        yield [
            Handler\ReleaseActivityHandler::class, [
                'active' => false,
            ], [], [
                'version' => ' v1 ',
            ]];

        yield [
            Handler\ReleaseActivityHandler::class, [], [], [
                'version' => 'v1',
            ]];

        foreach ([Handler\ApiAccessTokensCreateHandler::class, Handler\RegistryTokensCreateHandler::class] as $handler) {
            foreach ([[], [
                'name' => null,
            ], [
                'name' => [],
            ], [
                'name' => ' ',
            ]] as $payload) {
                yield [
                    $handler, $payload, [
                        'name' => ['Name is required.'],
                    ]];
            }

            yield [$handler, [
                'name' => ' Token ',
            ], []];
        }

        foreach ([Handler\AdminOrganizationUpdateHandler::class, Handler\AdminUserUpdateHandler::class, Handler\AdminSettingsUpdateHandler::class] as $handler) {
            yield [$handler, [], []];
        }

        foreach ([null, [], '', ' '] as $value) {
            yield [
                Handler\AdminOrganizationUpdateHandler::class, [
                    'name' => $value,
                ], [
                    'name' => ['Name is required.'],
                ]];

            yield [
                Handler\AdminOrganizationUpdateHandler::class, [
                    'slug' => $value,
                ], [
                    'slug' => ['Slug is invalid.'],
                ]];

            yield [
                Handler\AdminUserUpdateHandler::class, [
                    'email' => $value,
                ], [
                    'email' => ['Email is invalid.'],
                ]];

            yield [
                Handler\AdminUserUpdateHandler::class, [
                    'display_name' => $value,
                ], [
                    'display_name' => ['Display name is required.'],
                ]];
        }

        yield [
            Handler\AdminOrganizationUpdateHandler::class, [
                'slug' => 'bad/slug',
            ], [
                'slug' => ['Slug is invalid.'],
            ]];

        yield [
            Handler\AdminOrganizationUpdateHandler::class, [
                'anonymousAccess' => [],
            ], [
                'anonymous_access' => ['Anonymous access must be a boolean.'],
            ]];

        yield [Handler\AdminOrganizationUpdateHandler::class, [
            'slug'             => ' Valid-slug_123 ',
            'anonymous_access' => false,
        ], []];

        yield [Handler\AdminUserUpdateHandler::class, [
            'email'       => ' user@example.test ',
            'displayName' => ' User ',
        ], []];

        yield [Handler\AdminUserUpdateHandler::class, [
            'display_name' => 'Valid',
            'displayName'  => [],
        ], []];

        yield [
            Handler\AdminSettingsUpdateHandler::class, [
                'settings' => null,
            ], [
                'settings' => ['Settings must be an object.'],
            ]];

        yield [
            Handler\AdminSettingsUpdateHandler::class, [
                'settings' => 'invalid',
            ], [
                'settings' => ['Settings must be an object.'],
            ]];

        yield [Handler\AdminSettingsUpdateHandler::class, [
            'settings' => [],
        ], []];

        yield [Handler\AdminSettingsUpdateHandler::class, [
            'settings' => [
                'brand_name' => 'Packio',
            ],
        ], []];

        foreach ([[], [
            'provider' => [],
        ], [
            'provider' => '  ',
        ]] as $payload) {
            yield [
                Handler\ProviderAuthorizationsCreateHandler::class, $payload, [
                    'provider' => ['Provider is required.'],
                ]];
        }

        yield [
            Handler\ProviderAuthorizationsCreateHandler::class, [
                'provider' => 'github',
                'payload'  => null,
            ], [
                'payload' => ['Payload must be an object.'],
            ]];

        yield [Handler\ProviderAuthorizationsCreateHandler::class, [
            'provider' => 'github',
            'payload'  => [],
        ], []];

        yield [Handler\ProviderAuthorizationsCreateHandler::class, [
            'provider' => 'github',
        ], []];
        $valid = [
            'provider_type' => 'gitlab',
            'label'         => ' Team ',
            'base_url'      => 'https://gitlab.example.test',
            'auth_mode'     => 'token',
        ];
        foreach ([Handler\SourceProviderInstancesCreateHandler::class, Handler\SourceProviderInstanceUpdateHandler::class] as $handler) {
            yield [
                $handler, [], [
                    'source_provider_instance' => ['Provider type, label, base_url and auth_mode are required.'],
                ]];
            foreach (array_keys($valid) as $field) {
                foreach (['', null, []] as $value) {
                    yield [
                        $handler, array_replace($valid, [
                            $field => $value,
                        ]), [
                            'source_provider_instance' => ['Provider type, label, base_url and auth_mode are required.'],
                        ]];
                }
            }

            yield [$handler, $valid, []];

            yield [$handler, [
                'providerType' => 'gitlab',
                'label'        => 'Team',
                'baseUrl'      => 'https://gitlab.example.test',
                'authMode'     => 'token',
            ], []];
        }

        foreach ([Handler\OrganizationInvitationsCreateHandler::class, Handler\OrganizationMemberUpdateHandler::class] as $handler) {
            foreach ([[], [
                'role' => null,
            ], [
                'role' => [],
            ], [
                'role' => 'admin',
            ], [
                'role' => '  ',
            ]] as $payload) {
                yield [
                    $handler, $payload, [
                        'role' => ['Role must be member or owner.'],
                    ]];
            }

            yield [$handler, [
                'role' => ' owner ',
            ], []];

            yield [$handler, [
                'role' => 'member',
            ], []];
        }

        foreach ([Handler\OrganizationsCreateHandler::class, Handler\OrganizationUpdateHandler::class] as $handler) {
            foreach ([null, [], '', '  '] as $name) {
                yield [
                    $handler, [
                        'name' => $name,
                    ], [
                        'name' => ['Name is required.'],
                    ]];
            }

            yield [$handler, [
                'name' => ' Team ',
            ], []];
        }

        yield [
            Handler\OrganizationsCreateHandler::class, [], [
                'name' => ['Name is required.'],
            ]];

        yield [Handler\OrganizationUpdateHandler::class, [], []];

        yield [
            Handler\OrganizationUpdateHandler::class, [
                'anonymous_access' => 'nonsense',
            ], [
                'anonymous_access' => ['Anonymous access must be a boolean.'],
            ]];

        yield [Handler\OrganizationUpdateHandler::class, [
            'anonymousAccess' => false,
        ], []];

        yield [Handler\OrganizationUpdateHandler::class, [
            'anonymous_access' => false,
            'anonymousAccess'  => 'invalid',
        ], []];
    }

    public function testOmittedOrganizationPatchFieldsPreserveExistingValues(): void
    {
        $updateOrganizationRequest = new Request\UpdateOrganizationRequest();
        self::assertSame('Existing', $updateOrganizationRequest->name('Existing'));
        self::assertTrue($updateOrganizationRequest->anonymousAccess(true));
        self::assertFalse($updateOrganizationRequest->anonymousAccess(false));
    }

    public function testAdminPatchDefaultsAndRoleNormalizationArePreserved(): void
    {
        $updateAdminUserRequest = new Request\UpdateAdminUserRequest();
        self::assertSame('existing@example.test', $updateAdminUserRequest->email('existing@example.test'));
        self::assertSame('Existing', $updateAdminUserRequest->displayName('Existing'));
        self::assertSame(['admin'], $updateAdminUserRequest->roles(['admin']));
        self::assertSame(['admin'], new Request\UpdateAdminUserRequest([
            'roles' => [' admin ', 'unknown', false],
        ])->roles(['user']));
        self::assertSame(['user'], new Request\UpdateAdminUserRequest([
            'roles' => ['unknown'],
        ])->roles(['admin']));
        $updateAdminOrganizationRequest = new Request\UpdateAdminOrganizationRequest();
        self::assertSame('Existing', $updateAdminOrganizationRequest->name('Existing'));
        self::assertSame('existing', $updateAdminOrganizationRequest->slug('existing'));
        self::assertTrue($updateAdminOrganizationRequest->anonymousAccess(true));
    }

    public function testTokenListDefaultsAndAliasesArePreserved(): void
    {
        $api = new Request\CreateApiAccessTokenRequest([
            'name' => ' Token ',
        ]);
        self::assertSame('Token', $api->name());
        self::assertSame(['read'], $api->scopes());
        self::assertNull($api->expiresAt());
        $api = new Request\CreateApiAccessTokenRequest([
            'scopes'    => [' write ', false, ''],
            'expiresAt' => ' 2030-01-01 ',
        ]);
        self::assertSame(['write'], $api->scopes());
        self::assertSame('2030-01-01', $api->expiresAt());
        self::assertSame(['read'], new Request\CreateApiAccessTokenRequest([
            'scopes' => [],
        ])->scopes());
        $createRegistryTokenRequest = new Request\CreateRegistryTokenRequest();
        self::assertSame([RegistryProtocolPermissions::PACKAGES_READ_PRIVATE], $createRegistryTokenRequest->permissions());
        self::assertSame(['custom'], new Request\CreateRegistryTokenRequest([
            'permissions' => [' custom ', null],
        ])->permissions());
    }
}
