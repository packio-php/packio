# Releasing

A Packio release is a source tag `vX.Y.Z`, created with `composer create-release-tag`. Pushing the tag starts `.github/workflows/verify.yml`.

## Branches

- `develop` is the default branch. Changes reach it through squash-merged pull requests.
- `main` holds released code only. It changes through two kinds of pull request, both merged with a merge commit, never squashed:
  - a release pull request from `develop`, which carries the changelog;
  - a hotfix pull request from a branch cut from `main`.
- After every release or hotfix, merge `main` back into `develop` with a merge-commit pull request. The release tag sits on `main`, and without the back-merge `git describe` on `develop` does not see it.

## Creating the tag

After the pull request into `main` is merged:

```sh
git checkout main && git pull
composer create-release-tag
```

The script refuses to tag anything other than a local `main` that matches `origin/main`. `--dry-run` skips that check, so it can preview the next version on `develop`.

## What CI does on a tag

1. The usual checks run, including the `runtime` job, which builds the `prod`, `prod-worker` and `prod-ssr` images once and tests those exact images (layers, startup, notices, private Composer product).
2. If everything passes, the `publish` job pushes the same three images to GitHub Container Registry:
   - `ghcr.io/packio-php/packio`
   - `ghcr.io/packio-php/packio-worker`
   - `ghcr.io/packio-php/packio-ssr`

   Each is tagged `X.Y.Z`, `X.Y` and `latest`.

Pre-release tags (for example `v1.0.0-rc.1`) are not published. Images exist starting with 0.3.0; there are none for 0.1.0 or 0.2.0.

## Owner steps

- After the first publication, make the three packages public in the organization's package settings. New packages start private.
- To resume an interrupted publication, use **Re-run failed jobs** on that run, so `publish` reuses the verified images of that run. A fresh run of the same tag builds new images and is refused once the version is published: `publish` never replaces an existing `X.Y.Z`. Do not move the tag.

The images carry the project license and third-party notices. `THIRD_PARTY_NOTICES.md` says where the upstream source of the bundled third-party packages is; no source archive is published.
