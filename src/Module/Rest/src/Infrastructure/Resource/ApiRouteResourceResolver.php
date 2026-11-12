<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

use function is_string;
use function trim;

final class ApiRouteResourceResolver
{
    public static function routeString(ServerRequestInterface $serverRequest, string $attribute): string
    {
        $value = $serverRequest->getAttribute($attribute);

        return is_string($value) ? trim($value) : '';
    }

    public static function uuid(ServerRequestInterface $serverRequest, string $attribute): string
    {
        $value = self::routeString($serverRequest, $attribute);

        // canonical form: isValid() also accepts urn:uuid: and {braces}, which Postgres rejects
        return Uuid::isValid($value) ? Uuid::fromString($value)->toString() : '';
    }

    public static function userId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'userId');
    }

    public static function jobId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'jobId');
    }

    public static function tokenId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'tokenId');
    }

    public static function providerAuthorizationId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'authorizationId');
    }

    public static function sourceProviderInstanceId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'instanceId');
    }

    public static function memberId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'memberId');
    }

    public static function invitationId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'invitationId');
    }

    public static function organizationId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'organizationId');
    }

    public static function sourceId(ServerRequestInterface $serverRequest): string
    {
        return self::uuid($serverRequest, 'sourceId');
    }

    public static function organization(
        ServerRequestInterface $serverRequest,
        OrganizationApiFacadeInterface $organizationApiFacade
    ): ?OrganizationDto {
        $id = self::organizationId($serverRequest);

        return '' !== $id ? $organizationApiFacade->findOrganization($id) : null;
    }

    public static function providerAuthorization(
        ServerRequestInterface $serverRequest,
        SourceApiFacadeInterface $sourceApiFacade
    ): ?ProviderAuthorizationDto {
        $organizationDto  = RequestContextAttributes::requireCurrentOrganization($serverRequest);
        $id               = self::providerAuthorizationId($serverRequest);
        $authorization    = '' !== $id ? $sourceApiFacade->findProviderAuthorizationInOrganization($id, $organizationDto->id) : null;

        return $authorization instanceof ProviderAuthorizationDto && $authorization->organizationId === $organizationDto->id ? $authorization : null;
    }

    public static function packageName(ServerRequestInterface $serverRequest): string
    {
        $vendor  = self::routeString($serverRequest, 'vendor');
        $package = self::routeString($serverRequest, 'package');

        return '' !== $vendor && '' !== $package ? $vendor . '/' . $package : '';
    }

    public static function source(ServerRequestInterface $serverRequest, SourceApiFacadeInterface $sourceApiFacade): ?SourceDto
    {
        $organizationDto = RequestContextAttributes::requireCurrentOrganization($serverRequest);
        $sourceId        = self::sourceId($serverRequest);
        $source          = '' !== $sourceId ? $sourceApiFacade->findSource($sourceId) : null;

        return $source instanceof SourceDto && $source->organizationId === $organizationDto->id ? $source : null;
    }
}
