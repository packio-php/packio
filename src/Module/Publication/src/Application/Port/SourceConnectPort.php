<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Port;

use Packio\Publication\Application\Command\ConnectSourcesCommand;

interface SourceConnectPort
{
    public function authorize(ConnectSourcesCommand $connectSourcesCommand): string;

    /** @param array<string, mixed> $payload */
    public function schedule(array $payload, string $organizationId): bool;
}
