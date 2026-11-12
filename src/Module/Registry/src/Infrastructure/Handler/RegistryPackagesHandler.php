<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Infrastructure\ComposerRegistryResponseHeaders;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/packages.json', name: 'repo.public.packages')]
final readonly class RegistryPackagesHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryApiFacadeInterface $registryApiFacade,
        private RegistryMetadataUrlNormalizer $registryMetadataUrlNormalizer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);

        if (! $organization instanceof OrganizationDto) {
            return new JsonResponse([
                'error' => 'Organization not found.',
            ], 404);
        }

        if (! $organization->anonymousAccess) {
            return new JsonResponse([
                'error' => 'Anonymous access is disabled for this organization.',
            ], 401);
        }

        return ComposerRegistryResponseHeaders::metadata(
            new JsonResponse(
                $this->registryMetadataUrlNormalizer->normalize(
                    $this->registryApiFacade->publicRegistryPayload($organization->id),
                    $organization->slug,
                ),
            ),
        );
    }
}
