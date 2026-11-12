# Organization deletion

Organization deletion is asynchronous and multi-stage.

Organization settings, admin web deletion, and the admin API all call `OrganizationDeletionInitiator` through the Organization facade. Its input carries the organization, actor, origin (`organization`, `admin`, or `api`), and audit event. The application service coordinates held job creation, idempotent audit recording, and publication through an application port; HTTP handlers only map outcomes to their existing responses. Audit failures propagate after compensation is attempted for a newly initiated generation. Publication failure leaves the audited generation recoverable, and successful retries reuse the same job. The lower-level facade methods remain available for lifecycle recovery.

The HTTP action schedules `organization.delete` with stage `schedule_cleanup` and a `deletion_run_id`. That job marks the organization as `deleting`, removes invitations and members, revokes registry tokens, and schedules child cleanup jobs:

- `source.delete` for packages owned by a source;
- `package.delete` for packages without a scheduled source cleanup;
- `organization.delete` with stage `finalize`.

The finalize stage deletes the organization row only after child cleanup jobs for the same `deletion_run_id` are no longer pending, running, or failed. If archive cleanup cannot be scheduled or archive object deletion fails, the relevant cleanup job remains retryable or failed and the organization stays in `deleting`.

When an organization remains `deleting`, inspect Admin Jobs for the organization and match the `deletion_run_id` from the top-level `organization.delete` payload. Retry failed `package.delete` or `cleanup.package.archives` jobs after fixing the underlying storage/provider problem. Use the Source deletion recovery actions for `source.delete`; they schedule the current source generation (or atomically re-arm it for force recovery). Restarting a failed `organization.delete` stage through Admin Jobs is routed to the organization scheduler, which preserves the current generation and stage. Finalization can then be retried after cleanup jobs succeed.

## Deletion from the admin area

Administrators can initiate both organization and source deletion from the admin area. Both paths reuse the staged background jobs described above and require a typed confirmation plus a dependency preview before the destructive action.

### Organization deletion

The admin organization details page shows a danger zone with dependency counts (sources, packages, members, invitations). Deleting requires typing the exact organization name. The request is posted to `POST /admin/organizations/:organizationId/delete`, guarded by the `admin.access` permission. A successful initiation:

- atomically marks the organization `deleting` and stores a new deletion-run ID;
- creates the existing `organization.delete` job with stage `schedule_cleanup` in a non-executable `held` state (idempotency key `organization-delete:{organizationId}:{deletionRunId}`), records one idempotent audit entry `organization.delete.initiated_by_admin` keyed by that lifecycle generation, then publishes that exact job.

The UI disables delete initiation while the organization is already `deleting`. There is no admin cancel path for a running organization cleanup; failed child jobs are retried through the Admin Jobs UI.

### Source deletion

The admin sources page offers a `Delete` action for sources that are not `deleting`. The modal fetches `GET /admin/sources/:sourceId/deletion-preview` (`admin.sources.deletion-preview`) and shows the safe counts of linked packages and releases — no tokens, client secrets, configuration, emails, or OAuth identifiers are returned. Deleting requires typing the exact source name (`displayName` or `owner/repository`) and posts to `POST /admin/sources/:sourceId/delete` (`admin.sources.delete`, `admin.access` permission). A successful initiation:

- atomically marks the source `deleting` and stores a new deletion-run ID;
- creates the existing `source.delete` job in a non-executable held state with key `source-delete:{sourceId}:{deletionRunId}`, records one idempotent audit entry `source.delete.initiated_by_admin` keyed by that lifecycle generation, then publishes that exact job.

Admin and organization-scoped source deletion share the same job type and atomic lifecycle transition. The persisted deletion-run ID makes each new lifecycle distinct, so a terminal job from an earlier lifecycle cannot suppress or duplicate the next one. Sources already in `deleting` keep the existing recovery actions (requeue, force requeue, cancel, force finalize) and do not offer re-initiation.

Initiation is recoverable across process crashes: the `active` → `deleting` transition and its deletion-run ID are row-locked in the owning persistence boundary. A retry reconstructs a missing exact-generation held job, writes or proves the unique lifecycle audit entry, and advances the same job to `pending`. A held job cannot be claimed by a worker before this audit proof exists. If audit recording fails in a live request, the exact pre-initiation `status`/`active` state is restored only after cancellation of that specific held job succeeds; otherwise the resource remains deleting and the next retry resumes it safely. A destructive operation therefore never proceeds without its audit trail.

Force-requeue and source cleanup use an owning PostgreSQL advisory execution lease. The worker acquires the session lease and validates the source generation under a row lock before deleting packages, releases, archives, or the source itself; force-requeue takes the matching transaction lease before replacing a generation. Consequently, a force-requeue either replaces the generation before the worker starts (making the old job a no-op) or is rejected after execution starts. PostgreSQL automatically releases the session lease if the worker process or connection crashes, so recovery cannot be blocked by an orphaned flag.

## Upgrade ordering for lifecycle-generation migrations

Apply the lifecycle migrations before starting workers from the new release. The production deploy already enforces this ordering: migrations run while services still execute the previous release, and `packio-services-restart` starts the new worker code only afterward. This matters because a job payload already loaded into a process cannot be changed by a PostgreSQL backfill. For manual deployments, drain or stop workers before running these migrations, then start the new workers only after migration completion.
