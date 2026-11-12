<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use LogicException;
use Packio\Source\Application\Dto\SourceOAuthAuthorizationData;

final readonly class SourceOAuthAccountOwnershipDecision
{
    private function __construct(
        public SourceOAuthAccountOwnershipStatus $status,
        private ?SourceOAuthAuthorizationData $sourceOAuthAuthorizationData,
        public string $message,
    ) {}

    public static function allowed(SourceOAuthAuthorizationData $sourceOAuthAuthorizationData): self
    {
        return new self(
            SourceOAuthAccountOwnershipStatus::Allowed,
            $sourceOAuthAuthorizationData,
            '',
        );
    }

    public static function notFound(): self
    {
        return new self(
            SourceOAuthAccountOwnershipStatus::NotFound,
            null,
            'Source OAuth account not found.',
        );
    }

    public static function unavailable(): self
    {
        return new self(
            SourceOAuthAccountOwnershipStatus::Unavailable,
            null,
            'Source OAuth account is not available.',
        );
    }

    public static function tokenUnavailable(): self
    {
        return new self(
            SourceOAuthAccountOwnershipStatus::TokenUnavailable,
            null,
            'Source OAuth account token is missing or unreadable.',
        );
    }

    public function isAllowed(): bool
    {
        return SourceOAuthAccountOwnershipStatus::Allowed === $this->status;
    }

    public function requireAuthorizationData(): SourceOAuthAuthorizationData
    {
        if (! $this->sourceOAuthAuthorizationData instanceof SourceOAuthAuthorizationData) {
            throw new LogicException('Source OAuth account ownership is not allowed.');
        }

        return $this->sourceOAuthAuthorizationData;
    }
}
