<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Synchronizer;

use InvalidArgumentException;

use function sprintf;

final readonly class SourcePublicationSynchronizerRegistry
{
    /**
     * @param iterable<SourcePublicationSynchronizerInterface> $synchronizers
     */
    public function __construct(private iterable $synchronizers) {}

    public function forProvider(string $provider): SourcePublicationSynchronizerInterface
    {
        foreach ($this->synchronizers as $synchronizer) {
            if ($synchronizer->provider() === $provider) {
                return $synchronizer;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'Publication synchronizer for provider "%s" is not registered.',
                $provider,
            ),
        );
    }
}
