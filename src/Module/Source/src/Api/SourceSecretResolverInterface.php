<?php

declare(strict_types=1);

namespace Packio\Source\Api;

interface SourceSecretResolverInterface
{
    public function resolveSourceAuthorizationSecret(string $id, string $key = 'token'): ?string;

    public function resolveAuthorizationSecret(string $id): ?string;

    public function resolveWebhookSecret(string $id, string $organizationId): ?string;
}
