<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

use Packio\Publication\Application\Dto\PublicationSyncResult;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerInterface;

final readonly class ProviderSourcePublicationSynchronizer implements SourcePublicationSynchronizerInterface
{
    public function __construct(
        private ProviderPublicationSynchronizer $providerPublicationSynchronizer,
        private ProviderPublicationAdapterInterface $providerPublicationAdapter,
    ) {}

    public function provider(): string
    {
        return $this->providerPublicationAdapter->provider();
    }

    /**
     * @param array<string, mixed> $source
     */
    public function synchronize(array $source, ?string $accessToken = null): PublicationSyncResult
    {
        return $this->providerPublicationSynchronizer->synchronize(
            $source,
            $accessToken,
            $this->providerPublicationAdapter,
        );
    }
}
