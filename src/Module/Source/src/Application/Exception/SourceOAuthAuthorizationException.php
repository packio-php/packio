<?php

declare(strict_types=1);

namespace Packio\Source\Application\Exception;

use Packio\Source\Application\Service\SourceOAuthAccountOwnershipDecision;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipStatus;
use RuntimeException;

final class SourceOAuthAuthorizationException extends RuntimeException
{
    public function __construct(public readonly SourceOAuthAccountOwnershipStatus $status, string $message)
    {
        parent::__construct($message);
    }

    public static function fromDecision(SourceOAuthAccountOwnershipDecision $sourceOAuthAccountOwnershipDecision): self
    {
        return new self($sourceOAuthAccountOwnershipDecision->status, $sourceOAuthAccountOwnershipDecision->message);
    }
}
