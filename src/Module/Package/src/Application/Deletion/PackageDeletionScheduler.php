<?php

declare(strict_types=1);

namespace Packio\Package\Application\Deletion;

use Packio\Package\Application\Port\JobSchedulerPort;
use Packio\Package\Application\Query\PackageQueryService;
use Packio\Package\Domain\Entity\Package;

use function sprintf;
use function trim;

final readonly class PackageDeletionScheduler
{
    public const string JOB_TYPE = 'package.delete';

    public function __construct(
        private PackageQueryService $packageQueryService,
        private JobSchedulerPort $jobSchedulerPort,
    ) {}

    public function schedule(
        string $organizationId,
        string $name,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool {
        $resolvedOrganizationId = trim($organizationId);
        $resolvedName           = trim($name);
        if ('' === $resolvedOrganizationId || '' === $resolvedName) {
            return false;
        }

        if (! $this->packageQueryService->findPackageInOrganization($resolvedOrganizationId, $resolvedName) instanceof Package) {
            return false;
        }

        $resolvedDeletionRunId = null !== $deletionRunId ? trim($deletionRunId) : '';
        $resolvedOrigin        = trim($origin);
        $payload               = [
            'organization_id' => $resolvedOrganizationId,
            'package_name'    => $resolvedName,
        ];

        if ('' !== $resolvedDeletionRunId) {
            $payload['deletion_run_id'] = $resolvedDeletionRunId;
        }

        if ('' !== $resolvedOrigin) {
            $payload['origin'] = $resolvedOrigin;
        }

        $idempotencyKey = '' !== $resolvedDeletionRunId
            ? sprintf(
                'organization-delete:%s:%s:package:%s',
                $resolvedOrganizationId,
                $resolvedDeletionRunId,
                $resolvedName,
            )
            : sprintf('package-delete:%s:%s', $resolvedOrganizationId, $resolvedName);

        $this->jobSchedulerPort->enqueueOnce(
            organizationId: $resolvedOrganizationId,
            type: self::JOB_TYPE,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            priority: 10,
        );

        return true;
    }
}
