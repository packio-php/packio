<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use Common\Branding\BrandingConfig;
use Psr\Http\Message\ServerRequestInterface;

use function rtrim;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

final readonly class RegistryOrganizationResolver
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    public function slugFromRequest(ServerRequestInterface $serverRequest): ?string
    {
        return $this->slugFromHost($serverRequest->getUri()->getHost());
    }

    public function slugFromHost(string $host): ?string
    {
        $normalizedHost = rtrim(strtolower(trim($host)), '.');
        $baseDomain     = rtrim(
            strtolower(trim($this->brandingConfig->registryBaseDomain())),
            '.',
        );

        if ('' === $normalizedHost || '' === $baseDomain || $normalizedHost === $baseDomain) {
            return null;
        }

        $suffix = '.' . $baseDomain;
        if (! str_ends_with($normalizedHost, $suffix)) {
            return null;
        }

        $slug = substr($normalizedHost, 0, -strlen($suffix));
        if (
            '' === $slug
            || 'www' === $slug
            || str_contains($slug, '.')
        ) {
            return null;
        }

        return $slug;
    }
}
