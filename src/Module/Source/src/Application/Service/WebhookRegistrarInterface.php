<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Domain\Entity\Source;

interface WebhookRegistrarInterface
{
    /**
     * Register a webhook at the upstream provider for the given source.
     */
    public function register(Source $source): WebhookRegistrationResult;

    /**
     * Best-effort deletion of a previously registered webhook.
     * Returns true if the webhook was deleted or did not exist.
     */
    public function deregister(Source $source): bool;

    /**
     * Provider key this registrar supports (e.g. "github", "gitlab").
     */
    public function provider(): string;
}
