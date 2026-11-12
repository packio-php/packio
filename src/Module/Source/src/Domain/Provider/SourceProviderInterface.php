<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Provider;

use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;

interface SourceProviderInterface
{
    /**
     * Provider metadata exposed to the application/UI layer.
     */
    public function descriptor(): ProviderDescriptor;

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array;

    public function registerSource(
        string $organizationId,
        SourceRegistrationData $sourceRegistrationData,
        ?ProviderAuthorization $providerAuthorization = null
    ): Source;

    /**
     * Generate a new webhook secret for this provider.
     */
    public function generateWebhookSecret(): string;
}
