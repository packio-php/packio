<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Packio\Registry\Api\RegistryApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;

use function array_filter;
use function array_map;
use function array_values;

final readonly class RegistrySetupPageDataBuilder
{
    public function __construct(private RegistryApiFacadeInterface $registryApiFacade, private BrandingConfig $brandingConfig) {}

    /**
     * @return array{
     *     base_url: string,
     *     organization: array<string, mixed>,
     *     public_bootstrap: string,
     *     private_bootstrap: null|string,
     *     registry_host: string,
     *     public_packages: list<array{name: string, latest_version: null|string}>,
     *     private_packages: list<array{name: string, latest_version: null|string}>,
     * }
     */
    public function build(OrganizationDto $organizationDto, ?UserDto $userDto): array
    {
        $baseUrl      = $this->brandingConfig->registryBaseUrl($organizationDto->slug);
        $registryHost = $this->brandingConfig->registryHost($organizationDto->slug);
        $packages     = $this->registryApiFacade->packageOverview($organizationDto->id);

        $publicPackages = array_filter(
            $packages,
            static fn (array $package): bool => $package['active']
                && 'public' === $package['visibility'],
        );

        $privatePackages = $userDto instanceof UserDto
            ? array_filter(
                $packages,
                static fn (array $package): bool => $package['active']
                    && 'private' === $package['visibility'],
            )
            : [];

        return [
            'base_url'                  => $baseUrl,
            'organization'              => [
                'id'               => $organizationDto->id,
                'slug'             => $organizationDto->slug,
                'name'             => $organizationDto->name,
                'anonymous_access' => $organizationDto->anonymousAccess,
                'active'           => $organizationDto->active,
                'created_at'       => $organizationDto->createdAt,
                'updated_at'       => $organizationDto->updatedAt,
            ],
            'public_bootstrap'          => $baseUrl . '/packages.json',
            'private_bootstrap'         => $userDto instanceof UserDto
                ? $baseUrl . '/private/packages.json'
                : null,
            'registry_host'             => $registryHost,
            'public_packages'           => array_map(
                static fn (array $package): array => [
                    'name'           => $package['name'],
                    'latest_version' => $package['latest_version'],
                ],
                array_values($publicPackages),
            ),
            'private_packages'          => array_map(
                static fn (array $package): array => [
                    'name'           => $package['name'],
                    'latest_version' => $package['latest_version'],
                ],
                array_values($privatePackages),
            ),
        ];
    }
}
