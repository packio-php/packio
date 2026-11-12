<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Command;

use InvalidArgumentException;
use Packio\Shared\Domain\Organization\ActorContext;

use function preg_match;
use function trim;

final readonly class ConnectSourcesCommand
{
    /** @var non-empty-list<string> */
    public array $repositories;

    /**
     * @param list<string>          $repositories
     * @param array<string, string> $authorizationPayload
     * @param array<string, string> $defaultRefs
     * @param array<string, string> $settings
     */
    public function __construct(
        public ActorContext $actorContext,
        public string $provider,
        array $repositories,
        public string $authorizationId = '',
        public array $authorizationPayload = [],
        public array $defaultRefs = [],
        public string $visibility = 'public',
        public string $providerInstanceId = '',
        public int $keepLastReleases = 0,
        public array $settings = [],
    ) {
        if ([] === $repositories) {
            throw new InvalidArgumentException('Please select at least one repository.');
        }

        $this->repositories = $repositories;
        foreach ($repositories as $repository) {
            if ('' === trim($repository) || trim($repository) !== $repository || preg_match('/[\x00-\x20\x7f]/', $repository)) {
                throw new InvalidArgumentException('Invalid repository identifier.');
            }
        }
    }
}
