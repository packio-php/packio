<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Common\Security\StoredSecretResolver;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Command\AuthorizeProviderCommand;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use RuntimeException;

final readonly class SourceConnectAuthorizationResolver
{
    public function __construct(
        private SourceCommandService $sourceCommandService,
        private SourceAuthorizationStrategyResolver $sourceAuthorizationStrategyResolver,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private StoredSecretResolver $storedSecretResolver,
    ) {}

    /** @param array<string, string> $authorizationPayload */
    public function resolve(
        ActorContext $actorContext,
        string $provider,
        string $authorizationId,
        array $authorizationPayload,
        string $providerInstanceId,
    ): string {
        if ([] === $authorizationPayload) {
            if ('' !== $authorizationId) {
                $providerAuthorization = $this->providerAuthorizationRepository->findByIdInOrganization($authorizationId, $actorContext->organizationId);
                if (! $providerAuthorization instanceof ProviderAuthorization || $providerAuthorization->provider() !== $provider || 'active' !== $providerAuthorization->status()
                    || ('' !== $providerInstanceId && $providerAuthorization->providerInstanceId() !== $providerInstanceId)) {
                    throw new RuntimeException('Provider authorization is unavailable.');
                }
            }

            return $authorizationId;
        }

        foreach ($this->providerAuthorizationRepository->findByOrganization($actorContext->organizationId, $provider) as $providerAuthorization) {
            if ('active' !== $providerAuthorization->status() || $providerAuthorization->authStrategy() !== $this->sourceAuthorizationStrategyResolver->defaultFor($provider)) {
                continue;
            }
            $matches = true;
            foreach ($authorizationPayload as $key => $value) {
                $stored = 'token' === $key ? $this->storedSecretResolver->resolve($providerAuthorization->secret()) : ($providerAuthorization->configuration()[$key] ?? null);
                if ($stored !== $value) {
                    $matches = false;

                    break;
                }
            }

            if ($matches && ($providerAuthorization->providerInstanceId() ?? '') === $providerInstanceId) {
                return $providerAuthorization->getIdentifier()->toString();
            }
        }

        return $this->sourceCommandService->authorizeProviderInContext($actorContext, new AuthorizeProviderCommand(
            organizationId: $actorContext->organizationId,
            provider: $provider,
            authorizationStrategy: $this->sourceAuthorizationStrategyResolver->defaultFor($provider),
            payload: $authorizationPayload,
        ))->getIdentifier()->toString();
    }
}
