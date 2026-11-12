<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\DependencyInjection;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Packio\Publication\Application\Synchronizer\SourcePublicationSynchronizerRegistry;
use Packio\Publication\Infrastructure\Synchronizer\BitbucketPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\CodebergPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GenericGitPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GithubPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GitlabPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\GitlabSelfHostedPublicationAdapter;
use Packio\Publication\Infrastructure\Synchronizer\ProviderPublicationSynchronizer;
use Packio\Publication\Infrastructure\Synchronizer\ProviderSourcePublicationSynchronizer;
use Psr\Container\ContainerInterface;

use function array_map;

final class SourcePublicationSynchronizerRegistryFactory implements FactoryInterface
{
    /** @param null|array<string, mixed> $options */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): SourcePublicationSynchronizerRegistry {
        $synchronizer = $container->get(ProviderPublicationSynchronizer::class);

        return new SourcePublicationSynchronizerRegistry(array_map(
            static fn (string $adapter): ProviderSourcePublicationSynchronizer => new ProviderSourcePublicationSynchronizer(
                $synchronizer,
                $container->get($adapter),
            ),
            [
                GenericGitPublicationAdapter::class,
                GithubPublicationAdapter::class,
                GitlabPublicationAdapter::class,
                GitlabSelfHostedPublicationAdapter::class,
                BitbucketPublicationAdapter::class,
                CodebergPublicationAdapter::class,
            ],
        ));
    }
}
