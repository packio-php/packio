<?php

declare(strict_types=1);

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;

$projectRoot = \getcwd();
if (false === $projectRoot) {
    throw new RuntimeException('Unable to determine application directory.');
}

require $projectRoot . '/vendor/autoload.php';

$container = require $projectRoot . '/config/container.php';
$argv      = $_SERVER['argv'] ?? [];
$command   = $argv[1] ?? '';

if ('setup' === $command) {
    $identity = $container->get(IdentityApiFacadeInterface::class);
    $owner    = $identity->createUser('cold-worker-' . \bin2hex(\random_bytes(5)) . '@example.test', 'Cold Worker Owner', 'Password1!');
    $identity->markUserEmailAsVerified($owner->id);
    $organization = $container->get(OrganizationApiFacadeInterface::class)
        ->registerOrganization('cold-worker-' . \bin2hex(\random_bytes(5)), $owner->id)
    ;
    echo \json_encode([
        'owner'        => $owner->id,
        'organization' => $organization->id,
    ], JSON_THROW_ON_ERROR), "\n";

    exit;
}

if ('enqueue' === $command) {
    [$organizationId, $ownerId, $resource] = \array_slice($argv, 2, 3);
    $job                                   = $container->get(JobApiFacadeInterface::class)->enqueue(
        $organizationId,
        'source.create',
        [
            'organization_id'      => $organizationId,
            'requested_by_user_id' => $ownerId,
            'provider'             => 'generic_git',
            'resource'             => $resource,
        ],
    );
    echo \json_encode([
        'job' => $job->id,
    ], JSON_THROW_ON_ERROR), "\n";

    exit;
}

if ('status' === $command) {
    $job       = $container->get(JobApiFacadeInterface::class)->findById($argv[2]);
    $sourceIds = [];
    foreach ($container->get(SourceApiFacadeInterface::class)->allSources() as $source) {
        if ($source->organizationId === $argv[4]) {
            $sourceIds[] = $source->id;
        }
    }
    echo \json_encode([
        'status'     => $job?->status,
        'attempts'   => $job?->attemptCount,
        'worker_id'  => $job?->workerId,
        'error'      => $job?->errorMessage,
        'source_ids' => $sourceIds,
    ], JSON_THROW_ON_ERROR), "\n";

    exit;
}

\fwrite(STDERR, "Unknown fixture command.\n");

exit(1);
