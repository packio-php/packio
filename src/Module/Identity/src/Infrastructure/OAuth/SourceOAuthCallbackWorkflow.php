<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Api\SourceApiFacadeInterface;

final readonly class SourceOAuthCallbackWorkflow
{
    public function __construct(private SourceApiFacadeInterface $sourceApiFacade) {}

    public function handle(
        string $provider,
        string $code,
        string $userId,
        string $organizationSlug,
        string $providerInstanceId = '',
        ?string $codeVerifier = null
    ): SourceOAuthAccountDto {
        return '' !== $providerInstanceId
            ? $this->sourceApiFacade->handleSourceProviderInstanceOAuthCallback($provider, $code, $userId, $organizationSlug, $providerInstanceId, $codeVerifier)
            : $this->sourceApiFacade->handleSourceOAuthCallback($provider, $code, $userId, $organizationSlug, $codeVerifier);
    }
}
