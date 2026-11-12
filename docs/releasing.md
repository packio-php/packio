# Preparing a release

A Packio release is currently a source tag `vX.Y.Z`, which the maintainer creates with `composer create-release-tag`. Docker images are not published; the image bundle preparation described below is not used at the moment.

Packio release preparation is a manual, read-only process. The verification workflow builds and tests the app, worker and SSR images once; the release workflow consumes that exact successful run, prepares a source archive and a local preview manifest, and requests a separate owner review. Neither workflow pushes images, creates tags or publishes a GitHub release.

## Before starting

The repository must be `packio-php/packio`. Repository variables must contain the Docker Hub namespace `sirix777`, the version policy `native-semver`, and the maintainer's GitHub login in `RELEASE_OWNER_LOGIN`. Only that owner may launch or rerun preparation. Configure the `release-approval` environment separately with that maintainer as its only required reviewer, allow that owner to approve their own manual preview (`prevent_self_review: false`), and disable administrator bypass. The workflow reads these settings and stops if they are missing or differ; it does not create or change repository settings.

Use a full clean Git commit with the required support documentation. Run **Public verification** (`.github/workflows/verify.yml`) against the candidate and wait for its complete successful run. Save the run ID from the Actions page. The release workflow requires that run to come from this repository, use the `verify.yml` workflow, finish successfully, and have the exact same commit SHA selected for release preparation. A pull request run or a partial/skipped job set is rejected.

## Prepare a preview bundle

Open **Release bundle preparation** (`.github/workflows/release.yml`) and run it manually with:

- `candidate_ref`: the candidate branch, tag or full commit SHA;
- `verification_run_id`: the successful verification run ID.

The workflow resolves the selected ref once and uses that SHA for the verification-run comparison, source archive and manifest. Verification derives the version from native release metadata before building and applies the repository source, candidate revision, version and MIT license as OCI labels to all three images. It loads the saved runtime image exports; it does not rebuild them. It checks their image IDs and notices, creates source and metadata archives from the same clean commit, and passes the collected evidence to the local assembler. Review the resulting artifact and the exact SHA/version/checksums shown in the workflow summary before approving the protected environment step.

Version metadata comes from the candidate's Git history and existing release metadata. Preserve the project's semantic version. For Docker tag syntax only, the workflow maps `+` in build metadata to `-`; it does not change the manifest version, create a version tag or increment a version.

The `packio-preview-bundle` Actions artifact contains `release-manifest.json`, `SHA256SUMS`, and every referenced report, API snapshot, log, source archive and exact tested image export under `evidence/`. The workflow verifies the complete checksum file before upload. A `null` registry digest means the image has not been published. A ready local manifest describes a complete bundle prepared for owner review; it does not mean a release exists.

## Assemble and inspect evidence locally

The standard-library assembler accepts only a complete evidence directory bound to one clean candidate commit, the exact three image exports, and the required preview checks. It rejects missing or skipped results, mismatched SHA/image identities, unsafe paths, altered checksums, dirty source metadata, and attempts to replace an existing manifest with different content.

```sh
mkdir -p "$OUTPUT_DIR"
python3 bin/prepare-release.py \
  --level preview \
  --candidate-sha "$CANDIDATE_SHA" \
  --version "$VERSION" \
  --evidence-dir "$EVIDENCE_DIR" \
  --output-dir "$OUTPUT_DIR"
(cd "$OUTPUT_DIR" && sha256sum --check SHA256SUMS)
```

Keep `OUTPUT_DIR` separate from `EVIDENCE_DIR` so temporary source-archive files do not enter the checksummed bundle. The evidence directory must include `release-evidence.json`, the referenced reports and command logs, three OCI exports, and the source archive. Follow the per-file SHA-256 fields in the index; do not copy a report from another run or fill a missing check with a manual assertion. Build a clean source archive from the Git commit with `git archive`, generate `data/build/release.json` while that checkout still contains Git history, and add only the pinned corresponding-source payloads produced by `bin/prepare-notice-sources.py`. A source archive is for inspection and use; building production images from a Git-less source archive is unsupported.

The backup/restore runbook check establishes that the documented procedure is present in the candidate source. It is not a stable backup or recovery drill. Stable preparation additionally needs same-SHA readiness, limiter, recovery, SBOM and provenance evidence; the manual workflow does not produce those stable gates. Until those records exist and validate, the assembler must reject a stable bundle.

## Promotion boundary

Promotion is a separate owner-operated action. Before publishing, compare the registry image config and ordered layers with all three saved exports, record the resulting immutable registry digests, and verify that an existing version points to the same content before retrying. Do not rebuild after a candidate passes. A partial push remains incomplete until all three image digests are verified. Update a mutable `latest` tag only after every versioned artifact and source checksum is verified. No local `ready` status, workflow approval or prepared bundle authorizes publication.
