<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Request;

use InvalidArgumentException;
use Packio\Publication\Application\Command\ConnectSourcesCommand;
use Packio\Shared\Domain\Organization\ActorContext;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

final readonly class ConnectSourcesCommandFactory
{
    public function create(SourceConnectRequest $sourceConnectRequest, ActorContext $actorContext): ConnectSourcesCommand
    {
        $resources = $sourceConnectRequest->resourceUrls;
        if ([] === $resources) {
            $resources = ['' !== \trim($sourceConnectRequest->resourceUrl) ? $sourceConnectRequest->resourceUrl : $sourceConnectRequest->repositoryUrl];
        }
        $resources = array_values(array_unique(array_filter(array_map(trim(...), $resources), static fn (string $resource): bool => '' !== $resource)));
        if ([] === $resources) {
            throw new InvalidArgumentException('Please select at least one repository.');
        }
        $payload = [];
        if ('' !== $sourceConnectRequest->authorizationToken) {
            $payload['token'] = $sourceConnectRequest->authorizationToken;
            if ('gitlab_self_hosted' === $sourceConnectRequest->provider) {
                if ('' !== \trim($sourceConnectRequest->instanceUrl)) {
                    $payload['base_url'] = \trim($sourceConnectRequest->instanceUrl);
                }

                if ('' !== \trim($sourceConnectRequest->providerInstanceId)) {
                    $payload['provider_instance_id'] = \trim($sourceConnectRequest->providerInstanceId);
                }
            }

            if ('generic_git' === $sourceConnectRequest->provider) {
                $payload['auth_scheme'] = '' !== $sourceConnectRequest->authScheme ? $sourceConnectRequest->authScheme : 'bearer';
            }
        }
        $refs = [];
        foreach ($sourceConnectRequest->resourceDefaultRefs as $resource => $ref) {
            if ('' !== \trim((string) $resource) && '' !== \trim($ref)) {
                $refs[\trim((string) $resource)] = \trim($ref);
            }
        }

        return new ConnectSourcesCommand(
            actorContext: $actorContext,
            provider: $sourceConnectRequest->provider,
            repositories: $resources,
            authorizationId: \trim($sourceConnectRequest->authorizationId),
            authorizationPayload: $payload,
            defaultRefs: $refs,
            visibility: 'private' === $sourceConnectRequest->visibility ? 'private' : 'public',
            providerInstanceId: \trim($sourceConnectRequest->providerInstanceId),
            keepLastReleases: (int) $sourceConnectRequest->keepLastReleases,
            settings: 'generic_git' === $sourceConnectRequest->provider ? [
                'composer_json_path' => \trim($sourceConnectRequest->composerJsonPath) ?: 'composer.json',
            ] : [],
        );
    }
}
