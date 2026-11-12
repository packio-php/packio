<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Registry\Application\Service\VendorPackageInput;
use Packio\Registry\Infrastructure\ComposerRegistryResponseHeaders;
use Packio\Registry\Infrastructure\Middleware\RequireRegistryAccessMiddleware;
use Packio\Registry\Infrastructure\RegistryMetadataUrlNormalizer;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function is_string;
use function sprintf;

#[Get('/p2/:vendor/:package', name: 'repo.package.metadata', middleware: [RequireRegistryAccessMiddleware::class])]
final readonly class RegistryPackageMetadataHandler implements RequestHandlerInterface
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

        $vendor  = $request->getAttribute('vendor');
        $package = $request->getAttribute('package');

        if (! is_string($vendor) || '' === $vendor || ! is_string($package) || '' === $package) {
            return new JsonResponse([
                'error' => 'Invalid package name.',
            ], 400);
        }

        $vendorPackageInput = VendorPackageInput::fromSegments($vendor, $package);

        if (! $vendorPackageInput->isValid()) {
            return new JsonResponse(
                [
                    'error' => 'Invalid package name.',
                ],
                422,
            );
        }

        $name     = $vendorPackageInput->canonical;
        $metadata = $this->registryApiFacade->packageMetadata($name, $organization->id);

        if (null === $metadata) {
            return new JsonResponse([
                'error' => sprintf('Package "%s" is not indexed.', $name),
            ], 404);
        }

        return ComposerRegistryResponseHeaders::metadata(
            new JsonResponse($this->registryMetadataUrlNormalizer->normalize($metadata, $organization->slug)),
        );
    }
}
