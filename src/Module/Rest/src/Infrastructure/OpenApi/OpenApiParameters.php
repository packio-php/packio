<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'OrganizationPath', name: 'organization', description: 'Organization slug or UUID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'OrganizationIdPath', name: 'organizationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'UserIdPath', name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'MemberIdPath', name: 'memberId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'InvitationIdPath', name: 'invitationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'TokenIdPath', name: 'tokenId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'AuthorizationIdPath', name: 'authorizationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'InstanceIdPath', name: 'instanceId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'SourceIdPath', name: 'sourceId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'DeliveryIdPath', name: 'deliveryId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'JobIdPath', name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
#[OA\Parameter(parameter: 'VendorPath', name: 'vendor', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'PackagePath', name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'PageQuery', name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))]
#[OA\Parameter(parameter: 'PerPageQuery', name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1))]
#[OA\Parameter(parameter: 'SearchQuery', name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
#[OA\Parameter(parameter: 'UserStatusQuery', name: 'status', description: 'Filters users by account status.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'banned']))]
final class OpenApiParameters {}
