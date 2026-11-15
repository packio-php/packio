# Releasing

A Packio release is a source tag `vX.Y.Z`, created with `composer create-release-tag`. Pushing the tag starts `.github/workflows/verify.yml`.

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

The images carry the project license and third-party notices. `third-party-sources.json` lists the upstream source of every bundled third-party package; no source archive is published.
