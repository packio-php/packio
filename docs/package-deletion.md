# Package deletion

`PackageDeleteJobHandler` validates the payload, calls `PackageDeletionService`,
and maps its typed result to the Worker contract. The service owns the sequence:
check package existence, schedule archive cleanup, delete releases, delete package.

| Outcome | Meaning | Worker result |
| --- | --- | --- |
| `deleted` | Releases and package were deleted after cleanup scheduling succeeded. | Success |
| `already_deleted` | The package was absent at the initial lookup. No cleanup or deletion is attempted. | Success |
| `blocked` | Archive cleanup scheduling threw or reported at least one failure. Release and package metadata must be retained for retry. | Failure, retry enabled |
| `retryable_failure` | Package lookup or metadata deletion threw, or `deletePackage()` returned `false` after the package was initially found. | Failure, retry enabled |

`blocked` describes a scheduling barrier, not a permanent rejection. The cleanup
port does not distinguish permanent failures from transient failures. Both failure
outcomes use the existing Worker retry policy, with at most three attempts.
Malformed required payload fields remain non-retryable handler failures.

Retries inspect current storage state rather than maintaining a separate deletion
checkpoint. Cleanup is scheduled from the remaining release metadata before that
metadata is removed. A partial scheduling failure can enqueue duplicate cleanup
jobs on retry; archive deletion already tolerates repeated storage keys. Once
releases are removed, retry sees no remaining releases and continues to package
deletion. An absent package at the start of a later attempt is `already_deleted`;
a package disappearing during the current attempt is still a retryable failure.
Scheduling success does not mean asynchronous archive deletion has completed.

The deletion run ID and origin continue to propagate to cleanup jobs. Failure
results and warning contexts include organization ID, package name, deletion run
ID, and available progress counters. They exclude raw exception messages and
unrecognized payload fields. Counts describe the current attempt, not cumulative
progress across attempts.
