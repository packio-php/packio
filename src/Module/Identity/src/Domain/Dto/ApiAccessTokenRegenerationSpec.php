<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Dto;

use Packio\Identity\Domain\Entity\ApiAccessToken;

/**
 * Prepares the replacement token for a regeneration. The factory receives the
 * locked previous token and must return an issue-ready replacement entity.
 */
final readonly class ApiAccessTokenRegenerationSpec
{
    /** @var callable(ApiAccessToken): ApiAccessToken */
    private mixed $replacementFactory;

    /**
     * @param callable(ApiAccessToken): ApiAccessToken $replacementFactory
     */
    public function __construct(callable $replacementFactory)
    {
        $this->replacementFactory = $replacementFactory;
    }

    public function issueFor(ApiAccessToken $apiAccessToken): ApiAccessToken
    {
        return ($this->replacementFactory)($apiAccessToken);
    }
}
