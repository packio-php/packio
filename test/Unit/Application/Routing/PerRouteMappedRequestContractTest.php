<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing;

use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Application;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Packio\Admin\Infrastructure\Handler\AdminInvitationCreateHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminOrganizationUpdateHandler;
use Packio\Admin\Infrastructure\Handler\AdminSettingsUpdateHandler;
use Packio\Admin\Infrastructure\Handler\AdminSourceDeleteHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserBanHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserUnbanHandler;
use Packio\Admin\Infrastructure\Handler\AdminUserUpdateHandler;
use Packio\Application\Middleware\RateLimitMiddleware;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Identity\Infrastructure\Handler\ChangePasswordHandler;
use Packio\Identity\Infrastructure\Handler\DisconnectOAuthAccountHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationVerifyHandler;
use Packio\Identity\Infrastructure\Handler\ForgotPasswordHandler;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use Packio\Identity\Infrastructure\Handler\OAuthEmailSubmitHandler;
use Packio\Identity\Infrastructure\Handler\ProfileApiTokenIssueHandler;
use Packio\Identity\Infrastructure\Handler\RegisterHandler;
use Packio\Identity\Infrastructure\Handler\RegistryTokenIssueHandler;
use Packio\Identity\Infrastructure\Handler\ResetPasswordHandler;
use Packio\Identity\Infrastructure\Handler\ResetPasswordPageHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationCreateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationCreateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInviteAcceptHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInviteAcceptSubmitHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMemberRoleUpdateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsUpdateHandler;
use Packio\Package\Infrastructure\Handler\PackageDetailsHandler;
use Packio\Package\Infrastructure\Handler\SourcePackageActivityHandler;
use Packio\Publication\Infrastructure\Handler\SourceConnectHandler;
use Packio\Rest\Infrastructure\Handler\ApiAccessTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationInvitationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationMemberUpdateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\OrganizationUpdateHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationReassignDeleteHandler;
use Packio\Rest\Infrastructure\Handler\ProviderAuthorizationsCreateHandler;
use Packio\Rest\Infrastructure\Handler\RegistryTokensCreateHandler;
use Packio\Rest\Infrastructure\Handler\ReleaseActivityHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstancesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceProviderInstanceUpdateHandler;
use Packio\Rest\Infrastructure\Handler\SourcesCreateHandler;
use Packio\Rest\Infrastructure\Handler\SourceUpdateHandler;
use Packio\Source\Infrastructure\Handler\SourceArchiveHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationOAuthStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceProviderInstanceStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceSettingsUpdateHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryClassMapResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveryFileInventory;
use Sirix\Mezzio\Routing\Attributes\Discovery\PhpClassNameParser;
use Sirix\Mezzio\Routing\Attributes\Discovery\Psr4ClassNameResolver;
use Sirix\Mezzio\Routing\Attributes\Discovery\RoutableClassFilter;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractor;
use Sirix\Mezzio\Routing\Attributes\Extractor\ClassEligibilityValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\MethodSignatureValidator;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteAttributeReader;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDataNormalizer;
use Sirix\Mezzio\Routing\Attributes\Extractor\RouteDefinitionBuilder;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Sirix\Mezzio\Valinor\Middleware\ValinorRequestMapperMiddleware;
use Yiisoft\Validator\RulesProvider\AttributesRulesProvider;
use Yiisoft\Validator\RulesProviderInterface;

use function array_count_values;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function dirname;
use function implode;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function iterator_to_array;
use function sprintf;
use function trim;

/**
 * Explicit per-route contract:
 *
 * - `#[MapRequest]` owns structural mapping metadata only;
 * - `#[ValidateMappedRequest]` is the only switch that enables Yiisoft semantic validation;
 * - `#[ValidationFailed]` never registers middleware and only overrides the failure response policy;
 * - mapper and validator are never piped globally;
 * - a mapped DTO carrying real Yiisoft rules must have a matching validation entry.
 */
#[AllowMockObjectsWithoutExpectations]
final class PerRouteMappedRequestContractTest extends TestCase
{
    private const string MAPPER_MIDDLEWARE = ValinorRequestMapperMiddleware::class;

    private const string VALIDATOR_MIDDLEWARE = ValidateMappedRequestMiddleware::class;

    private const string MAPPINGS_KEY = 'valinor_mappings';

    private const string VALIDATIONS_KEY = 'mapped_request_validations';

    private const string POLICIES_KEY = 'validation_failed';

    /**
     * The complete set of handler classes that must stay discoverable as mapped
     * routes. Deleting `#[MapRequest]` from a handler must not silently shrink
     * the discovery set.
     *
     * @var list<class-string>
     */
    private const array EXPECTED_MAPPED_HANDLERS = [
        AdminInvitationCreateHandler::class,
        AdminOrganizationDeleteHandler::class,
        AdminOrganizationUpdateHandler::class,
        AdminSettingsUpdateHandler::class,
        AdminSourceDeleteHandler::class,
        AdminUserBanHandler::class,
        AdminUserUnbanHandler::class,
        AdminUserUpdateHandler::class,
        ChangePasswordHandler::class,
        DisconnectOAuthAccountHandler::class,
        EmailVerificationVerifyHandler::class,
        ForgotPasswordHandler::class,
        LoginHandler::class,
        OAuthEmailSubmitHandler::class,
        ProfileApiTokenIssueHandler::class,
        RegisterHandler::class,
        RegistryTokenIssueHandler::class,
        ResetPasswordHandler::class,
        ResetPasswordPageHandler::class,
        OrganizationCreateHandler::class,
        OrganizationInvitationCreateHandler::class,
        OrganizationInviteAcceptHandler::class,
        OrganizationInviteAcceptSubmitHandler::class,
        OrganizationMemberRoleUpdateHandler::class,
        OrganizationSettingsDeleteHandler::class,
        OrganizationSettingsUpdateHandler::class,
        PackageDetailsHandler::class,
        SourcePackageActivityHandler::class,
        SourceConnectHandler::class,
        \Packio\Release\Infrastructure\Handler\ReleaseActivityHandler::class,
        \Packio\Rest\Infrastructure\Handler\AdminOrganizationUpdateHandler::class,
        \Packio\Rest\Infrastructure\Handler\AdminSettingsUpdateHandler::class,
        \Packio\Rest\Infrastructure\Handler\AdminUserUpdateHandler::class,
        ApiAccessTokensCreateHandler::class,
        OrganizationInvitationsCreateHandler::class,
        OrganizationMemberUpdateHandler::class,
        OrganizationUpdateHandler::class,
        OrganizationsCreateHandler::class,
        ProviderAuthorizationDeleteHandler::class,
        ProviderAuthorizationReassignDeleteHandler::class,
        ProviderAuthorizationsCreateHandler::class,
        RegistryTokensCreateHandler::class,
        ReleaseActivityHandler::class,
        SourceProviderInstanceUpdateHandler::class,
        SourceProviderInstancesCreateHandler::class,
        SourceUpdateHandler::class,
        SourcesCreateHandler::class,
        SourceArchiveHandler::class,
        SourceAuthorizationDeleteHandler::class,
        SourceAuthorizationOAuthStoreHandler::class,
        SourceAuthorizationStoreHandler::class,
        SourceDeleteHandler::class,
        SourceProviderInstanceStoreHandler::class,
        \Packio\Source\Infrastructure\Handler\SourceProviderInstanceUpdateHandler::class,
        SourceSettingsUpdateHandler::class,
    ];

    #[Test]
    public function itKeepsTheMapperAndTheValidatorOutOfTheGlobalPipeline(): void
    {
        $configuredMiddleware = $this->configuredGlobalPipelineMiddleware();

        self::assertNotContains(
            self::MAPPER_MIDDLEWARE,
            $configuredMiddleware,
            'The Valinor request mapper middleware must be registered per route, not globally.',
        );
        self::assertNotContains(
            self::VALIDATOR_MIDDLEWARE,
            $configuredMiddleware,
            'The mapped request validator middleware must be registered per route, not globally.',
        );
    }

    #[Test]
    public function itDiscoversTheCompleteMappedHandlerManifest(): void
    {
        $discoveredHandlers = [];

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            if (! array_key_exists(self::MAPPINGS_KEY, $routeDefinition->defaults)) {
                continue;
            }

            $discoveredHandlers[$routeDefinition->handlerService] = true;
        }

        self::assertSame(
            self::EXPECTED_MAPPED_HANDLERS,
            array_keys($discoveredHandlers),
            'The mapped handler manifest changed. Update EXPECTED_MAPPED_HANDLERS deliberately, never silently.',
        );
        self::assertCount(
            55,
            self::EXPECTED_MAPPED_HANDLERS,
            'The manifest size is part of the contract; changing it requires an explicit review.',
        );
    }

    #[Test]
    public function itRegistersTheMapperExactlyOnceOnEveryMappedRoute(): void
    {
        $violations       = [];
        $mappedRouteCount = 0;

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $mappings           = $routeDefinition->defaults[self::MAPPINGS_KEY] ?? null;
            $mapperPositions    = $this->middlewarePositions($routeDefinition, self::MAPPER_MIDDLEWARE);
            $validatorPositions = $this->middlewarePositions($routeDefinition, self::VALIDATOR_MIDDLEWARE);

            if (null !== $mappings) {
                ++$mappedRouteCount;

                if (1 !== count($mapperPositions)) {
                    $violations[] = sprintf(
                        '%s: mapped route must carry the mapper middleware exactly once, got %d.',
                        $this->describe($routeDefinition),
                        count($mapperPositions),
                    );
                }
            } elseif ([] !== $mapperPositions) {
                $violations[] = sprintf(
                    '%s: carries the mapper middleware without valinor_mappings metadata.',
                    $this->describe($routeDefinition),
                );
            }

            if (null !== $mappings && [] !== $validatorPositions && [] === $this->validationEntries($routeDefinition)) {
                $violations[] = sprintf(
                    '%s: carries the validator middleware without mapped_request_validations metadata.',
                    $this->describe($routeDefinition),
                );
            }

            if (null === $mappings && [] !== $this->validationEntries($routeDefinition)) {
                $violations[] = sprintf(
                    '%s: carries mapped_request_validations metadata without valinor_mappings.',
                    $this->describe($routeDefinition),
                );
            }

            if (null === $mappings && array_key_exists(self::POLICIES_KEY, $routeDefinition->defaults)) {
                $violations[] = sprintf(
                    '%s: carries validation_failed policy metadata without valinor_mappings.',
                    $this->describe($routeDefinition),
                );
            }
        }

        self::assertSame([], $violations);
        self::assertGreaterThan(0, $mappedRouteCount, 'No mapped routes were discovered; the extractor contract is broken.');
    }

    #[Test]
    public function itValidatesEveryRuleBearingMappedDtoAndLeavesRuleFreeDtosMapperOnly(): void
    {
        $violations             = [];
        $validatedMappingCount  = 0;
        $mapperOnlyMappingCount = 0;

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $mappings = $routeDefinition->defaults[self::MAPPINGS_KEY] ?? null;

            if (null === $mappings) {
                continue;
            }

            $validationEntries = $this->validationEntries($routeDefinition);

            foreach ($mappings as $mapping) {
                $dtoClass = $this->mappingDto($mapping);
                if (null === $dtoClass || ! class_exists($dtoClass)) {
                    continue;
                }

                $requiresValidation = $this->dtoDeclaresRules($dtoClass);
                $hasValidation      = $this->mappingHasMatchingValidation($mapping, $validationEntries);

                if ($requiresValidation && ! $hasValidation) {
                    $violations[] = sprintf(
                        '%s: mapped DTO %s declares Yiisoft rules but has no matching #[ValidateMappedRequest] entry.',
                        $this->describe($routeDefinition),
                        $dtoClass,
                    );

                    continue;
                }

                if ($hasValidation) {
                    ++$validatedMappingCount;
                } else {
                    ++$mapperOnlyMappingCount;
                }
            }
        }

        self::assertSame([], $violations);
        self::assertSame(51, $validatedMappingCount, 'The rule-bearing validation target inventory changed.');
        self::assertSame(5, $mapperOnlyMappingCount, 'The mapper-only DTO inventory changed.');
    }

    #[Test]
    public function itRegistersTheValidatorExactlyOnceAfterTheMapperOnlyForExplicitValidations(): void
    {
        $violations = [];

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $mapperPositions    = $this->middlewarePositions($routeDefinition, self::MAPPER_MIDDLEWARE);
            $validatorPositions = $this->middlewarePositions($routeDefinition, self::VALIDATOR_MIDDLEWARE);
            $validationEntries  = $this->validationEntries($routeDefinition);

            if ([] !== $validationEntries) {
                if (1 !== count($validatorPositions)) {
                    $violations[] = sprintf(
                        '%s: route with mapped_request_validations must carry the validator middleware exactly once, got %d.',
                        $this->describe($routeDefinition),
                        count($validatorPositions),
                    );

                    continue;
                }

                if ([] === $mapperPositions) {
                    $violations[] = sprintf('%s: validator present without the mapper.', $this->describe($routeDefinition));

                    continue;
                }

                if ($mapperPositions[0] > $validatorPositions[0]) {
                    $violations[] = sprintf('%s: validator runs before the mapper.', $this->describe($routeDefinition));
                }
            } elseif ([] !== $validatorPositions) {
                $violations[] = sprintf(
                    '%s: mapper-only route carries the validator middleware.',
                    $this->describe($routeDefinition),
                );
            }
        }

        self::assertSame([], $violations);
    }

    #[Test]
    public function itNeverRegistersTheValidatorThroughValidationFailed(): void
    {
        $violations = [];

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $policies = $routeDefinition->defaults[self::POLICIES_KEY] ?? null;

            if (null === $policies) {
                continue;
            }

            $validatorPositions = $this->middlewarePositions($routeDefinition, self::VALIDATOR_MIDDLEWARE);

            if ([] === $this->validationEntries($routeDefinition) && [] !== $validatorPositions) {
                $violations[] = sprintf(
                    '%s: validation_failed without mapped_request_validations must not register the validator middleware.',
                    $this->describe($routeDefinition),
                );
            }
        }

        self::assertSame([], $violations);
    }

    #[Test]
    public function itGeneratesFullKeyValidationEntriesWithConsistentTargets(): void
    {
        $violations  = [];
        $entryCount  = 0;

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $validationEntries = $this->validationEntries($routeDefinition);

            if ([] === $validationEntries) {
                continue;
            }

            $effectiveKeys = [];

            foreach ($validationEntries as $validationEntry) {
                ++$entryCount;

                $missingKeys = array_diff(['dto'], array_keys($validationEntry));
                $extraKeys   = array_diff(array_keys($validationEntry), ['dto']);

                if ([] !== $missingKeys) {
                    $violations[] = sprintf(
                        '%s: mapped_request_validations entry missing keys: %s.',
                        $this->describe($routeDefinition),
                        implode(', ', $missingKeys),
                    );
                }

                if ([] !== $extraKeys) {
                    $violations[] = sprintf(
                        '%s: mapped_request_validations entry has unexpected keys: %s.',
                        $this->describe($routeDefinition),
                        implode(', ', $extraKeys),
                    );
                }

                $dto = $validationEntry['dto'] ?? null;

                if (! is_string($dto) || '' === $dto || trim($dto) !== $dto) {
                    $violations[] = sprintf(
                        '%s: mapped_request_validations entry must declare a non-empty dto class-string.',
                        $this->describe($routeDefinition),
                    );

                    continue;
                }

                $effective       = $dto;
                $effectiveKeys[] = $effective;

                $matchedMapping = $this->findMatchingMapping($routeDefinition, $dto, $effective);

                if (null === $matchedMapping) {
                    $violations[] = sprintf(
                        '%s: validation entry targets %s (output %s) with no compatible valinor_mappings entry.',
                        $this->describe($routeDefinition),
                        $dto,
                        $effective,
                    );
                }
            }

            $duplicates = array_filter(
                array_count_values($effectiveKeys),
                static fn (int $count): bool => $count > 1,
            );

            if ([] !== $duplicates) {
                $violations[] = sprintf(
                    '%s: duplicate effective validation targets %s.',
                    $this->describe($routeDefinition),
                    implode(', ', array_map(strval(...), array_keys($duplicates))),
                );
            }
        }

        self::assertSame([], $violations);
        self::assertGreaterThan(0, $entryCount, 'No mapped_request_validations entries were discovered; the extractor contract is broken.');
    }

    #[Test]
    public function itGeneratesUniqueOutputKeysWithinEveryRoutesValinorMappings(): void
    {
        $violations = [];

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $mappings = $routeDefinition->defaults[self::MAPPINGS_KEY] ?? null;

            if (null === $mappings) {
                continue;
            }

            self::assertIsArray($mappings);

            $outputKeys = [];

            foreach ($mappings as $mapping) {
                self::assertIsArray($mapping);
                self::assertArrayHasKey('methods', $mapping);

                foreach (['body', 'query', 'route', 'source'] as $sourceKey) {
                    $value = $mapping[$sourceKey] ?? null;

                    if (null !== $value) {
                        $outputKeys[] = $mapping['output'] ?? $value;
                    }
                }
            }

            $duplicates = array_filter(
                array_count_values($outputKeys),
                static fn (int $count): bool => $count > 1,
            );

            if ([] !== $duplicates) {
                $violations[] = sprintf('%s: duplicate output keys %s.', $this->describe($routeDefinition), implode(', ', array_map(strval(...), array_keys($duplicates))));
            }
        }

        self::assertSame([], $violations);
    }

    #[Test]
    public function itMapsEveryValinorMappingEntryThroughThePackageOptionsContract(): void
    {
        $sourceKeys  = ['body', 'query', 'route', 'source'];
        $allowedKeys = [...$sourceKeys, 'output', 'errorResponder', 'methods'];
        $violations  = [];
        $entryCount  = 0;

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $mappings = $routeDefinition->defaults[self::MAPPINGS_KEY] ?? null;

            if (null === $mappings) {
                continue;
            }

            self::assertIsList($mappings);

            foreach ($mappings as $mapping) {
                ++$entryCount;
                self::assertIsArray($mapping);

                $actualKeys  = array_keys($mapping);
                $extraKeys   = array_diff($actualKeys, $allowedKeys);
                $missingKeys = array_diff($allowedKeys, $actualKeys);

                if ([] !== $extraKeys) {
                    $violations[] = sprintf(
                        '%s: unexpected valinor_mappings keys: %s.',
                        $this->describe($routeDefinition),
                        implode(', ', $extraKeys),
                    );
                }

                if ([] !== $missingKeys) {
                    $violations[] = sprintf(
                        '%s: missing valinor_mappings keys: %s.',
                        $this->describe($routeDefinition),
                        implode(', ', $missingKeys),
                    );
                }

                $selectedSources = [];

                foreach ($sourceKeys as $sourceKey) {
                    $value = $mapping[$sourceKey] ?? null;

                    if (null !== $value) {
                        $selectedSources[] = $sourceKey;
                    }
                }

                if (1 !== count($selectedSources)) {
                    $violations[] = sprintf(
                        '%s: valinor_mappings entry must select exactly one source key, got %d (%s).',
                        $this->describe($routeDefinition),
                        count($selectedSources),
                        implode(', ', $selectedSources),
                    );
                }
            }
        }

        self::assertSame([], $violations);
        self::assertGreaterThan(0, $entryCount, 'No valinor_mappings metadata entries were discovered; the extractor contract is broken.');
    }

    #[Test]
    public function itPlacesTheRateLimiterBeforeTheMapperAndTheValidator(): void
    {
        $violations        = [];
        $guardedRouteCount = 0;

        foreach ($this->extractRouteDefinitions() as $routeDefinition) {
            $rateLimiterPositions = $this->middlewarePositions($routeDefinition, RateLimitMiddleware::class);

            if ([] === $rateLimiterPositions) {
                continue;
            }

            $mapperPositions    = $this->middlewarePositions($routeDefinition, self::MAPPER_MIDDLEWARE);
            $validatorPositions = $this->middlewarePositions($routeDefinition, self::VALIDATOR_MIDDLEWARE);

            if ([] === $mapperPositions && [] === $validatorPositions) {
                continue;
            }

            ++$guardedRouteCount;

            if ([] !== $mapperPositions && $rateLimiterPositions[0] > $mapperPositions[0]) {
                $violations[] = sprintf(
                    '%s: rate limiter must run before the request mapper.',
                    $this->describe($routeDefinition),
                );
            }

            if ([] !== $validatorPositions && $rateLimiterPositions[0] > $validatorPositions[0]) {
                $violations[] = sprintf(
                    '%s: rate limiter must run before the request validator.',
                    $this->describe($routeDefinition),
                );
            }
        }

        self::assertSame([], $violations);
        self::assertGreaterThan(0, $guardedRouteCount, 'No rate-limited mapped routes were discovered; the guard contract is broken.');
    }

    /**
     * @return list<int>
     */
    private function middlewarePositions(RouteDefinition $routeDefinition, string $middleware): array
    {
        $positions = [];

        foreach ($routeDefinition->middlewareServices as $index => $service) {
            if ($middleware === $service) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validationEntries(RouteDefinition $routeDefinition): array
    {
        $entries = $routeDefinition->defaults[self::VALIDATIONS_KEY] ?? [];

        if (! is_array($entries)) {
            return [];
        }

        return array_values($entries);
    }

    private function mappingDto(mixed $mapping): ?string
    {
        if (! is_array($mapping)) {
            return null;
        }

        foreach (['body', 'query', 'route', 'source'] as $sourceKey) {
            $value = $mapping[$sourceKey] ?? null;

            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>       $mapping
     * @param list<array<string, mixed>> $validationEntries
     */
    private function mappingHasMatchingValidation(array $mapping, array $validationEntries): bool
    {
        $dto = $this->mappingDto($mapping);

        if (null === $dto) {
            return false;
        }

        $effectiveOutput = $mapping['output'] ?? $dto;

        foreach ($validationEntries as $validationEntry) {
            $entryDto = $validationEntry['dto'] ?? null;

            if ($entryDto === $dto && $entryDto === $effectiveOutput) {
                return true;
            }
        }

        return false;
    }

    private function findMatchingMapping(RouteDefinition $routeDefinition, string $dto, string $effective): mixed
    {
        foreach ($routeDefinition->defaults[self::MAPPINGS_KEY] ?? [] as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            if ($this->mappingDto($mapping) !== $dto) {
                continue;
            }

            $mappingOutput = $mapping['output'] ?? $this->mappingDto($mapping);

            if ($mappingOutput === $effective) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @param class-string $dtoClass
     */
    private function dtoDeclaresRules(string $dtoClass): bool
    {
        if (is_a($dtoClass, RulesProviderInterface::class, true)) {
            return true;
        }

        $rules = new AttributesRulesProvider($dtoClass)->getRules();

        return [] !== iterator_to_array($rules, preserve_keys: false);
    }

    /**
     * @return list<string>
     */
    private function configuredGlobalPipelineMiddleware(): array
    {
        $configuredMiddleware = [];
        $middlewareFactory    = $this->createMock(MiddlewareFactoryInterface::class);
        $middlewareFactory->method('prepare')->willReturnCallback(
            static function(mixed $middleware) use (&$configuredMiddleware): MiddlewareInterface {
                $configuredMiddleware[] = $middleware;

                return new class implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return $handler->handle($request);
                    }
                };
            },
        );
        $application = new Application(
            $middlewareFactory,
            new MiddlewarePipe(),
            $this->createMock(RouteCollectorInterface::class),
            $this->createMock(RequestHandlerRunnerInterface::class),
        );
        $pipeline    = require dirname(__DIR__, 4) . '/config/pipeline.php';

        $pipeline(
            $application,
            $middlewareFactory,
            $this->createMock(ContainerInterface::class),
        );

        $names = [];
        foreach ($configuredMiddleware as $m) {
            $names[] = is_object($m) ? $m::class : (string) $m;
        }

        return $names;
    }

    /**
     * @return list<RouteDefinition>
     */
    private function extractRouteDefinitions(): array
    {
        $discovery = $this->discoveryConfig();

        $discoveryClassMapResolver = new DiscoveryClassMapResolver(
            'psr4',
            true,
            new DiscoveryFileInventory($discovery['paths']),
            new PhpClassNameParser(),
            new Psr4ClassNameResolver($discovery['psr4']['mappings']),
            new RoutableClassFilter(),
        );

        $routeAttributeReader    = new RouteAttributeReader();
        $attributeRouteExtractor = new AttributeRouteExtractor(
            new ClassEligibilityValidator(false),
            $routeAttributeReader,
            new RouteDefinitionBuilder(
                $routeAttributeReader,
                new MethodSignatureValidator(),
                new RouteDataNormalizer(),
            ),
        );

        return $attributeRouteExtractor->extract($discoveryClassMapResolver->resolve());
    }

    private function describe(RouteDefinition $routeDefinition): string
    {
        return sprintf(
            '%s %s (%s::%s)',
            null === $routeDefinition->methods ? 'ANY' : implode('|', $routeDefinition->methods),
            $routeDefinition->path,
            $routeDefinition->handlerService,
            $routeDefinition->handlerMethod,
        );
    }

    /**
     * @return array{paths: list<non-empty-string>, psr4: array{mappings: array<non-empty-string, non-empty-string>}}
     */
    private function discoveryConfig(): array
    {
        $config    = require dirname(__DIR__, 4) . '/config/config.php';
        $discovery = $config['routing_attributes']['discovery'];

        $paths    = [];
        $mappings = [];

        foreach ($discovery['paths'] as $path) {
            if (is_string($path) && '' !== $path) {
                $paths[] = $path;
            }
        }

        foreach ($discovery['psr4']['mappings'] as $prefix => $directory) {
            if (is_string($prefix) && '' !== $prefix && is_string($directory) && '' !== $directory) {
                $mappings[$prefix] = $directory;
            }
        }

        return [
            'paths' => $paths,
            'psr4'  => [
                'mappings' => $mappings,
            ],
        ];
    }
}
