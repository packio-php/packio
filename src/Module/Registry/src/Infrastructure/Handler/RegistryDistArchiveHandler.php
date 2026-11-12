<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Package\Api\Event\PackageDownloaded;
use Packio\Registry\Application\Exception\ReleaseArchiveFetchFailedException;
use Packio\Registry\Application\Exception\ReleaseArchiveStorageUnavailableException;
use Packio\Registry\Application\Service\ReleaseArchiveStorageService;
use Packio\Registry\Application\Service\VendorPackageInput;
use Packio\Registry\Application\Storage\ReleaseArchiveDownload;
use Packio\Registry\Infrastructure\ArchiveContentDisposition;
use Packio\Registry\Infrastructure\ComposerRegistryResponseHeaders;
use Packio\Registry\Infrastructure\Middleware\RequireRegistryAccessMiddleware;
use Packio\Shared\Api\Dto\OrganizationDto;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_pop;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function str_ends_with;
use function substr;

#[Get('/dist/:vendor/:package/:archive*', name: 'repo.package.dist.reference', middleware: [RequireRegistryAccessMiddleware::class])]
final readonly class RegistryDistArchiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private ReleaseArchiveStorageService $releaseArchiveStorageService,
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);
        if (! $organization instanceof OrganizationDto) {
            $response = $this->responseFactory->createResponse(404);
            $response->getBody()->write('Organization not found.');

            return $response->withHeader('Content-Type', 'text/plain');
        }

        $vendor    = $request->getAttribute('vendor');
        $package   = $request->getAttribute('package');
        $archive   = $request->getAttribute('archive');

        if (
            ! is_string($vendor) || '' === $vendor
            || ! is_string($package) || '' === $package
            || ! is_string($archive) || '' === $archive
        ) {
            $response = $this->responseFactory->createResponse(400);
            $response->getBody()->write('Invalid request.');

            return $response->withHeader('Content-Type', 'text/plain');
        }

        $vendorPackageInput = VendorPackageInput::fromSegments($vendor, $package);

        if (! $vendorPackageInput->isValid()) {
            $response = $this->responseFactory->createResponse(422);
            $response->getBody()->write('Invalid package name.');

            return $response->withHeader('Content-Type', 'text/plain');
        }

        $archiveSegments = explode('/', $archive);
        if (count($archiveSegments) < 2) {
            return $this->plainTextResponse(400, 'Invalid archive reference.');
        }

        $reference = array_pop($archiveSegments);
        if (in_array('', $archiveSegments, true) || '' === $reference) {
            return $this->plainTextResponse(400, 'Invalid archive reference.');
        }

        $version = implode('/', $archiveSegments);
        if (! str_ends_with($reference, '.zip')) {
            return $this->plainTextResponse(400, 'Invalid archive reference.');
        }

        $name              = $vendorPackageInput->canonical;
        $expectedReference = substr($reference, 0, -4);
        if ('' === $expectedReference) {
            return $this->plainTextResponse(400, 'Invalid archive reference.');
        }

        try {
            $archiveDownload = $this->releaseArchiveStorageService->downloadArchive($organization->id, $name, $version, $expectedReference);
        } catch (ReleaseArchiveFetchFailedException) {
            return $this->plainTextResponse(502, 'Archive could not be fetched from upstream.');
        } catch (ReleaseArchiveStorageUnavailableException) {
            return $this->plainTextResponse(503, 'Archive storage is temporarily unavailable.');
        }

        if (! $archiveDownload instanceof ReleaseArchiveDownload) {
            $response = $this->responseFactory->createResponse(404);
            $response->getBody()->write(sprintf('Archive for "%s" version "%s" is not available.', $name, $version));

            return $response->withHeader('Content-Type', 'text/plain');
        }

        $this->eventDispatcher->dispatch(new PackageDownloaded(
            $organization->id,
            $name,
            $version,
        ));

        $response = ComposerRegistryResponseHeaders::archive(
            $this->responseFactory
                ->createResponse(200)
                ->withHeader('Content-Type', $archiveDownload->contentType)
                ->withHeader('Content-Disposition', ArchiveContentDisposition::attachment($archiveDownload->fileName)),
        );
        if (null !== $archiveDownload->size) {
            $response = $response->withHeader('Content-Length', (string) $archiveDownload->size);
        }

        return $response->withBody($archiveDownload->body);
    }

    private function plainTextResponse(int $statusCode, string $body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/plain');
    }
}
