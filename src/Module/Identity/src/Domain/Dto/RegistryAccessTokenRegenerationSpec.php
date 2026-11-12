<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Dto;

use Packio\Identity\Domain\Entity\RegistryAccessToken;

/**
 * Prepares the replacement token for a regeneration. The factory receives the
 * locked previous token and must return an issue-ready replacement entity.
 */
final readonly class RegistryAccessTokenRegenerationSpec
{
    /** @var callable(RegistryAccessToken): RegistryAccessToken */
    private mixed $replacementFactory;

    /**
     * @param callable(RegistryAccessToken): RegistryAccessToken $replacementFactory
     */
    public function __construct(callable $replacementFactory)
    {
        $this->replacementFactory = $replacementFactory;
    }

    public function issueFor(RegistryAccessToken $registryAccessToken): RegistryAccessToken
    {
        return ($this->replacementFactory)($registryAccessToken);
    }
}
