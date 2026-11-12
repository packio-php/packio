#!/usr/bin/env python3
"""Check release images and source archives retain required distribution notices."""

import argparse
import hashlib
import json
from pathlib import Path
import posixpath
import re
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[3]
REQUIRED = (
    "LICENSE",
    "THIRD_PARTY_NOTICES.md",
    "third-party-sources.json",
    "public/fonts/LICENSE-JetBrainsMono.txt",
    "public/fonts/LICENSE-PlusJakartaSans.txt",
    "public/api-doc/assets/LICENSE",
    "public/api-doc/assets/NOTICE",
    "public/api-doc/assets/THIRD_PARTY_NOTICES.md",
    "public/api-doc/assets/swagger-ui-bundle.js.LICENSE.txt",
    "public/api-doc/assets/swagger-ui-standalone-preset.js.LICENSE.txt",
)
SWAGGER_ASSETS = (
    "swagger-ui.css",
    "swagger-ui-bundle.js",
    "swagger-ui-standalone-preset.js",
    "favicon-16x16.png",
    "favicon-32x32.png",
)
FONT_ASSETS = (
    "jetbrains-mono-cyrillic.woff2",
    "jetbrains-mono-latin.woff2",
    "jetbrains-mono-latin-ext.woff2",
    "plus-jakarta-sans-latin.woff2",
    "plus-jakarta-sans-latin-ext.woff2",
    "plus-jakarta-sans-cyrillic-ext.woff2",
)
SWAGGER_VERSION = "5.32.8"
SWAGGER_BUNDLE_SHA256 = "97f03cdae8b9f09f8f33b604ed4796bd318c378e90763b468bb4aa0860bd90a7"
SWAGGER_STANDALONE_SHA256 = "3b5efc014c67162d35ea0870a8ddb0096c0bc5092b72cb27ff48a289ecfb8983"
SWAGGER_UPSTREAM_COMMIT = "4e0d3f88de7db0395a80ef0541ff428f0926d484"
SWAGGER_SOURCE_ARCHIVE_SHA256 = "ca41262ad814d6a7f08ba17c1b3fbd52e022982114754a1779cf49b2fa343872"
SWAGGER_UPSTREAM_LOCK_SHA256 = "01fd554046206999ffbb6c7b7eac3be068d8cf2a76bc334b82dcfdf936015819"
SWAGGER_COMPONENTS_SHA256 = "f6eecc9854bdc64e858c417e945f5ee50d276155bf543709172280ff9e959329"
SWAGGER_LICENSE_IDS_SHA256 = "5253c58ee667ce0a2055516170649ad2d675e6fc83e1d0c68a4c924c5aa6af04"
SWAGGER_GENERATED_RUNTIME_SHA256 = "acfa084eb0130ccf03d85160cdd533f2f67de584299fb8b3cb88464dec951b02"
SOURCE_METADATA = (
    ".docker/Dockerfile.roadrunner",
    "bin/prepare-notice-sources.py",
    "docs/testing.md",
)
ASSETS = tuple(f"public/api-doc/assets/{name}" for name in SWAGGER_ASSETS) + tuple(
    f"public/fonts/{name}" for name in FONT_ASSETS
)
CHECKED_FILES = REQUIRED + ASSETS


def check_checkout():
    missing = [path for path in REQUIRED if not (ROOT / path).is_file()]
    if missing:
        raise AssertionError("checkout is missing required notice files: " + ", ".join(missing))
    missing_assets = [path for path in ASSETS if not (ROOT / path).is_file()]
    if missing_assets:
        raise AssertionError("checkout is missing inventoried assets: " + ", ".join(missing_assets))
    missing_source_metadata = [path for path in SOURCE_METADATA if not (ROOT / path).is_file()]
    if missing_source_metadata:
        raise AssertionError("checkout is missing source companion metadata: " + ", ".join(missing_source_metadata))
    return {path: (ROOT / path).read_bytes() for path in CHECKED_FILES}


def check_installed_swagger(files):
    lock = json.loads((ROOT / "package-lock.json").read_text())
    locked = lock.get("packages", {}).get("node_modules/swagger-ui-dist", {}).get("version")
    package = json.loads((ROOT / "node_modules/swagger-ui-dist/package.json").read_text())
    installed = package.get("version")
    if locked != SWAGGER_VERSION or installed != SWAGGER_VERSION:
        raise AssertionError(
            f"swagger-ui-dist version mismatch: expected={SWAGGER_VERSION}, lock={locked!r}, installed={installed!r}"
        )
    for name in SWAGGER_ASSETS:
        installed_path = ROOT / "node_modules/swagger-ui-dist" / name
        checkout_path = ROOT / "public/api-doc/assets" / name
        if not installed_path.is_file():
            raise AssertionError(f"checkout Swagger asset differs from installed {installed} package: {name}")
        installed_bytes = installed_path.read_bytes()
        if installed_bytes != files[str(checkout_path.relative_to(ROOT))]:
            raise AssertionError(f"checkout Swagger asset differs from installed {installed} package: {name}")
        expected_digest = {
            "swagger-ui-bundle.js": SWAGGER_BUNDLE_SHA256,
            "swagger-ui-standalone-preset.js": SWAGGER_STANDALONE_SHA256,
        }.get(name)
        if expected_digest and hashlib.sha256(installed_bytes).hexdigest() != expected_digest:
            raise AssertionError(f"installed Swagger asset identity changed: {name}")
    for name in ("LICENSE", "NOTICE", "swagger-ui-bundle.js.LICENSE.txt",
                 "swagger-ui-standalone-preset.js.LICENSE.txt"):
        installed_path = ROOT / "node_modules/swagger-ui-dist" / name
        if not installed_path.is_file() or installed_path.read_bytes() != files[f"public/api-doc/assets/{name}"]:
            raise AssertionError(f"Swagger {name} differs from installed {installed} package")
    check_swagger_companion(files["public/api-doc/assets/THIRD_PARTY_NOTICES.md"])
    return installed


def canonical_digest(value):
    payload = json.dumps(value, ensure_ascii=False, separators=(",", ":")).encode()
    return hashlib.sha256(payload).hexdigest()


def check_swagger_companion(content):
    text = content.decode("utf-8")
    version_match = re.search(r"^Applies to the distributed Swagger UI ([^ ]+) assets:$", text, re.MULTILINE)
    asset_hashes = dict(re.findall(
        r"^- `(swagger-ui-bundle\.js|swagger-ui-standalone-preset\.js)` — SHA-256 `([0-9a-f]{64})`$",
        text, re.MULTILINE))
    source_match = re.search(
        r"^Swagger UI source tag `([^`]+)`, commit `([0-9a-f]{40})` \(archive SHA-256 `([0-9a-f]{64})`\)\.",
        text, re.MULTILINE)
    lock_match = re.search(r"The exact upstream lockfile SHA-256 is `([0-9a-f]{64})`\.", text)
    if (not version_match or version_match.group(1) != SWAGGER_VERSION
            or asset_hashes != {"swagger-ui-bundle.js": SWAGGER_BUNDLE_SHA256,
                                "swagger-ui-standalone-preset.js": SWAGGER_STANDALONE_SHA256}
            or not source_match
            or source_match.groups() != ("v" + SWAGGER_VERSION, SWAGGER_UPSTREAM_COMMIT,
                                         SWAGGER_SOURCE_ARCHIVE_SHA256)
            or not lock_match or lock_match.group(1) != SWAGGER_UPSTREAM_LOCK_SHA256):
        raise AssertionError("Swagger notice companion has the wrong asset or source identity")

    def section(name, next_name=None):
        heading = f"## {name}"
        if text.count(heading) != 1:
            raise AssertionError(f"Swagger notice companion must contain one {heading} section")
        start = text.index(heading) + len(heading)
        end = text.find(f"\n## {next_name}", start) if next_name else -1
        return text[start:end if end >= 0 else None]

    component_rows = []
    for line in section("Bundled components", "Generated runtime").splitlines():
        if not line.startswith("|") or line.startswith("|---") or "Package" in line:
            continue
        cells = [cell.strip() for cell in line.strip().strip("|").split("|")]
        if len(cells) != 5:
            raise AssertionError("Swagger component table must have five columns")
        values = [cell[1:-1] if cell.startswith("`") and cell.endswith("`") else cell for cell in cells[:4]]
        refs = re.findall(r"`([^`]+)`", cells[4])
        if not all(values) or not refs:
            raise AssertionError("Swagger component row is missing source identity or license document IDs")
        component_rows.append([values[0], values[1], values[2], values[3], sorted(refs)])
    if not component_rows or len({row[0] for row in component_rows}) != len(component_rows):
        raise AssertionError("Swagger component table is empty or repeats a package")
    component_rows.sort(key=lambda row: row[0])
    if canonical_digest(component_rows) != SWAGGER_COMPONENTS_SHA256:
        raise AssertionError("Swagger component inventory differs from the verified emitted-source graph")

    generated = section("Generated runtime", "License texts")
    match = re.search(r"Webpack `([^`]+)` \(integrity `([^`]+)`\), license document `([^`]+)`\.", generated)
    generated_rows = [["webpack", match.group(1), match.group(2), [match.group(3)]]] if match else []
    if canonical_digest(generated_rows) != SWAGGER_GENERATED_RUNTIME_SHA256:
        raise AssertionError("Swagger generated-runtime attribution differs from verified source")

    license_texts = section("License texts")
    headings = list(re.finditer(r"^### `([^`]+)`\s*$", license_texts, re.MULTILINE))
    ids = [match.group(1) for match in headings]
    if len(ids) != len(set(ids)) or canonical_digest(sorted(ids)) != SWAGGER_LICENSE_IDS_SHA256:
        raise AssertionError("Swagger license document IDs differ from the verified notice catalog")
    for index, heading in enumerate(headings):
        end = headings[index + 1].start() if index + 1 < len(headings) else len(license_texts)
        body = license_texts[heading.end():end]
        full_text = re.search(r"````text\s*\n(.*?)\n````", body, re.DOTALL)
        if not full_text or not full_text.group(1).strip():
            raise AssertionError(f"Swagger license document has no terms: {heading.group(1)}")
    referenced = {ref for row in component_rows for ref in row[4]} | {generated_rows[0][3][0]}
    if not referenced.issubset(set(ids)):
        raise AssertionError("Swagger package attribution references a missing license text")


def verify_runtime_versions(expected, output):
    actual_packages, runtime_versions = {}, []
    for line in output.splitlines():
        fields = line.split("\t")
        if len(fields) == 2 and fields[0] == "runtime":
            runtime_versions.append(fields[1])
        elif len(fields) == 4 and fields[0] not in actual_packages:
            actual_packages[fields[0]] = tuple(fields[1:])
        elif len(fields) == 4:
            raise AssertionError(f"runtime query repeated Debian package {fields[0]}")
        else:
            raise AssertionError(f"invalid runtime version output: {line!r}")
    expected_packages = {
        item["package"]: (item["version"], item["source_package"], item["source_version"])
        for item in expected["debian_packages"]
    }
    if actual_packages != expected_packages:
        changed = sorted(name for name in expected_packages.keys() | actual_packages.keys()
                         if expected_packages.get(name) != actual_packages.get(name))
        raise AssertionError("installed Debian package versions differ: " + ", ".join(changed[:8])
                             + (f" (+{len(changed) - 8} more)" if len(changed) > 8 else ""))
    expected_runtime = expected.get("php_version", expected.get("node_version"))
    if runtime_versions != [expected_runtime]:
        raise AssertionError(f"installed runtime version differs: expected={expected_runtime}, actual={runtime_versions}")


def verify_runtime_files(expected, output):
    actual = {}
    for line in output.splitlines():
        digest, separator, path = line.partition("  ")
        if not separator or path in actual:
            raise AssertionError(f"invalid retained notice hash output: {line!r}")
        actual[path] = digest
    expected_files = {**expected["retained_notices"], **expected["binary_files"]}
    if actual != expected_files:
        changed = sorted(path for path in expected_files.keys() | actual.keys()
                         if expected_files.get(path) != actual.get(path))
        raise AssertionError("runtime file hashes differ: " + ", ".join(changed[:8])
                             + (f" (+{len(changed) - 8} more)" if len(changed) > 8 else ""))


def check_runtime_versions(image, expected):
    query = ("dpkg-query -W -f='${Package}\\t${Version}\\t${source:Package}\\t${source:Version}\\n'; "
             "printf 'runtime\\t'; ")
    if "php_version" in expected:
        query += "php -r 'echo PHP_VERSION;'"
    else:
        query += "node -p 'process.versions.node'"
    result = subprocess.run(("docker", "run", "--pull=never", "--rm", "--entrypoint", "sh",
                             image, "-ec", query),
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=False)
    output = result.stdout.decode(errors="replace")
    if result.returncode:
        raise RuntimeError(f"runtime version query failed for {image}: {output}")
    verify_runtime_versions(expected, output)
    notice_paths = list(expected["retained_notices"]) + list(expected["binary_files"])
    notice_query = subprocess.run(("docker", "run", "--pull=never", "--rm", "--entrypoint", "sh",
                                    image, "-ec", "sha256sum \"$@\"", "sh", *notice_paths),
                                   stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=False)
    notice_output = notice_query.stdout.decode(errors="replace")
    if notice_query.returncode:
        raise RuntimeError(f"runtime file hash query failed for {image}: {notice_output}")
    verify_runtime_files(expected, notice_output)


def normalized(name):
    return "/" + posixpath.normpath(name).lstrip("/")


def apply_layer(files, layer, targets):
    with tarfile.open(fileobj=layer, mode="r:*") as archive:
        members = archive.getmembers()
        for member in members:
            path = normalized(member.name)
            base = posixpath.basename(path)
            parent = posixpath.dirname(path)
            if base == ".wh..wh..opq":
                prefix = parent.rstrip("/") + "/"
                for previous in [name for name in files if name.startswith(prefix)]:
                    del files[previous]
            elif base.startswith(".wh."):
                removed = posixpath.join(parent, base[4:])
                for previous in [name for name in files
                                 if name == removed or name.startswith(removed.rstrip("/") + "/")]:
                    del files[previous]
        for member in members:
            path = normalized(member.name)
            base = posixpath.basename(path)
            if base.startswith(".wh."):
                continue
            descendants = [target for target in targets if target.startswith(path.rstrip("/") + "/")]
            if descendants and not member.isdir():
                for target in descendants:
                    files.pop(target, None)
            if path not in targets:
                continue
            if member.isfile():
                content = archive.extractfile(member)
                if content is not None:
                    files[path] = content.read()
            else:
                files.pop(path, None)


def image_files(image, expected, prefix="/app"):
    targets = {normalized(posixpath.join(prefix, path)) for path in expected}
    with tempfile.TemporaryDirectory(prefix="release-notices-") as directory:
        archive_path = Path(directory) / "image.tar"
        result = subprocess.run(("docker", "save", "--output", str(archive_path), image),
                                stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=False)
        if result.returncode:
            raise RuntimeError(f"docker save failed for {image}: {result.stdout.decode(errors='replace')}")
        files = {}
        with tarfile.open(archive_path, mode="r:*") as exported:
            manifest = json.load(exported.extractfile("manifest.json"))[0]
            for layer_name in manifest.get("Layers", []):
                layer = exported.extractfile(layer_name)
                if layer is None:
                    raise AssertionError(f"{image}: saved image is missing layer {layer_name}")
                apply_layer(files, layer, targets)
        return files


def source_files(archive_path, expected):
    targets = {normalized(path) for path in expected}
    files = {}
    seen = set()
    with tarfile.open(archive_path, mode="r:*") as archive:
        for member in archive:
            path = normalized(member.name)
            if path not in targets:
                continue
            if path in seen:
                raise AssertionError(f"source archive repeats expected path: {path}")
            seen.add(path)
            if not member.isfile():
                raise AssertionError(f"source archive expected path is not a regular file: {path}")
            content = archive.extractfile(member)
            if content is not None:
                files[path] = content.read()
    return files


def source_hashes(archive_path, expected):
    targets = {normalized(item["path"]): item for item in expected}
    hashes, seen = {}, set()
    with tarfile.open(archive_path, mode="r:*") as archive:
        for member in archive:
            path = normalized(member.name)
            if path not in targets:
                continue
            if path in seen:
                raise AssertionError(f"source archive repeats expected path: {path}")
            seen.add(path)
            if not member.isfile():
                raise AssertionError(f"source archive expected path is not a regular file: {path}")
            content = archive.extractfile(member)
            if content is None:
                raise AssertionError(f"source archive could not read expected path: {path}")
            digest = hashlib.sha256()
            size = 0
            for chunk in iter(lambda: content.read(1024 * 1024), b""):
                digest.update(chunk)
                size += len(chunk)
            hashes[path] = (size, digest.hexdigest())
    return hashes


def source_manifest():
    manifest = json.loads((ROOT / "third-party-sources.json").read_text())
    if (manifest.get("schema") != 1 or not isinstance(manifest.get("sources"), list)
            or not manifest["sources"]):
        raise AssertionError("unsupported third-party source manifest")
    sources, paths = [], set()
    for item in manifest["sources"]:
        path = item.get("path")
        digest = item.get("sha256")
        size = item.get("size")
        if (not isinstance(path, str) or not path.startswith("third-party-sources/")
                or normalized(path) != "/" + path or path in paths
                or type(size) is not int or size < 0
                or not isinstance(digest, str) or len(digest) != 64
                or any(character not in "0123456789abcdef" for character in digest)):
            raise AssertionError(f"invalid or duplicate source manifest entry: {item!r}")
        paths.add(path)
        sources.append(item)
    runtime = manifest.get("runtime")
    if not isinstance(runtime, dict) or set(runtime) != {"app", "worker", "ssr"}:
        raise AssertionError("source manifest must bind runtime versions for app, worker, and ssr")
    for name, expected in runtime.items():
        if not isinstance(expected, dict):
            raise AssertionError(f"source manifest has invalid runtime metadata for {name}")
        packages = expected.get("debian_packages")
        if not isinstance(packages, list) or not packages:
            raise AssertionError(f"source manifest has no Debian package versions for {name}")
        if any(not isinstance(package, dict) for package in packages):
            raise AssertionError(f"invalid Debian package source mapping for {name}")
        names = [package.get("package") for package in packages]
        if any(not isinstance(package_name, str) or not package_name for package_name in names):
            raise AssertionError(f"invalid Debian package names for {name}")
        if names != sorted(names) or len(names) != len(set(names)):
            raise AssertionError(f"source manifest Debian packages must be sorted and unique for {name}")
        for package in packages:
            if any(not isinstance(package.get(field), str) or not package[field]
                   for field in ("package", "version", "source_package", "source_version")):
                raise AssertionError(f"invalid Debian package source mapping for {name}: {package!r}")
            if not re.fullmatch(r"[A-Za-z0-9.+:-]+", package["package"]):
                raise AssertionError(f"invalid Debian package name for {name}: {package['package']!r}")
        expected_runtime = "php_version" if name in ("app", "worker") else "node_version"
        other_runtime = "node_version" if expected_runtime == "php_version" else "php_version"
        if (not isinstance(expected.get(expected_runtime), str) or not expected[expected_runtime]
                or other_runtime in expected):
            raise AssertionError(f"source manifest has an invalid runtime version for {name}")
        notices = expected.get("retained_notices")
        if not isinstance(notices, dict) or not notices:
            raise AssertionError(f"source manifest has no retained notice hashes for {name}")
        binaries = expected.get("binary_files")
        if not isinstance(binaries, dict) or not binaries:
            raise AssertionError(f"source manifest has no runtime binary hashes for {name}")
        for label, file_hashes in (("retained notice", notices), ("runtime binary", binaries)):
            if any(not isinstance(path, str) or not path.startswith("/")
                   or not isinstance(digest, str)
                   or not re.fullmatch(r"[0-9a-f]{64}", digest)
                   for path, digest in file_hashes.items()):
                raise AssertionError(f"source manifest has invalid {label} hashes for {name}")
        if notices.keys() & binaries.keys():
            raise AssertionError(f"source manifest repeats runtime file paths for {name}")
    bindings = manifest.get("debian_sources")
    if not isinstance(bindings, list) or not bindings:
        raise AssertionError("source manifest has no Debian source bindings")
    required_pairs = {(package["source_package"], package["source_version"])
                      for expected in runtime.values() for package in expected["debian_packages"]}
    bound_pairs = set()
    for binding in bindings:
        if not isinstance(binding, dict):
            raise AssertionError("invalid Debian source binding")
        pair = (binding.get("package"), binding.get("version"))
        payloads = binding.get("paths")
        if (pair in bound_pairs or pair not in required_pairs or not isinstance(payloads, list)
                or not payloads or any(not isinstance(path, str) for path in payloads)
                or len(payloads) != len(set(payloads)) or not set(payloads).issubset(paths)
                or sum(path.endswith(".dsc") for path in payloads) != 1):
            raise AssertionError(f"invalid or incomplete Debian source binding: {pair!r}")
        bound_pairs.add(pair)
    if bound_pairs != required_pairs:
        raise AssertionError("Debian source bindings do not cover all installed source versions")
    return manifest


def check_source_archive(archive_path, files, manifest=None):
    metadata = {path: (ROOT / path).read_bytes() for path in SOURCE_METADATA}
    expected = {**files, **metadata}
    manifest = manifest or source_manifest()
    sources = manifest["sources"]
    archive_files = source_files(archive_path, expected)
    missing, mismatched = [], []
    for path, content in expected.items():
        actual = archive_files.get(normalized(path))
        if actual is None:
            missing.append(path)
        elif hashlib.sha256(actual).digest() != hashlib.sha256(content).digest():
            mismatched.append(path)
    payload_hashes = source_hashes(archive_path, sources)
    missing_sources, mismatched_sources = [], []
    for item in sources:
        path = normalized(item["path"])
        actual = payload_hashes.get(path)
        if actual is None:
            missing_sources.append(item["path"])
        elif actual != (item["size"], item["sha256"]):
            mismatched_sources.append(item["path"])
    if missing or mismatched or missing_sources or mismatched_sources:
        details = []
        if missing:
            details.append("missing files: " + ", ".join(missing))
        if mismatched:
            details.append("file hash mismatch: " + ", ".join(mismatched))
        if missing_sources:
            details.append("missing source payloads: " + ", ".join(missing_sources))
        if mismatched_sources:
            details.append("source payload size/hash mismatch: " + ", ".join(mismatched_sources))
        raise AssertionError("source archive: " + "; ".join(details))


def check_artifact(label, artifact_files, expected, prefix=""):
    missing, mismatched = [], []
    for path, content in expected.items():
        archive_path = normalized(posixpath.join(prefix, path))
        actual = artifact_files.get(archive_path)
        if actual is None:
            missing.append(path)
        elif hashlib.sha256(actual).digest() != hashlib.sha256(content).digest():
            mismatched.append(path)
    details = []
    if missing:
        details.append("missing: " + ", ".join(missing))
    if mismatched:
        details.append("hash mismatch: " + ", ".join(mismatched))
    if details:
        raise AssertionError(f"{label}: " + "; ".join(details))


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--checkout-only", action="store_true", help="check the current checkout only")
    parser.add_argument("--source-archive-only", action="store_true", help="check one source archive only")
    parser.add_argument("--app-image")
    parser.add_argument("--worker-image")
    parser.add_argument("--ssr-image")
    parser.add_argument("--source-archive")
    args = parser.parse_args(argv)
    files = check_checkout()
    swagger_version = check_installed_swagger(files)
    if args.checkout_only:
        if args.source_archive_only:
            parser.error("--checkout-only cannot be combined with --source-archive-only")
        print(f"checkout notices and assets verified (swagger-ui-dist {swagger_version})")
        return
    if args.source_archive_only:
        if not args.source_archive:
            parser.error("--source-archive-only requires --source-archive")
        check_source_archive(args.source_archive, files)
        print("source archive notices and source companions verified")
        return
    missing = [name for name in ("app_image", "worker_image", "ssr_image", "source_archive")
               if not getattr(args, name)]
    if missing:
        parser.error("requires --app-image, --worker-image, --ssr-image, and --source-archive")
    manifest = source_manifest()
    images = (("app", args.app_image), ("worker", args.worker_image), ("ssr", args.ssr_image))
    for name, image in images:
        check_runtime_versions(image, manifest["runtime"][name])
    for name, image in images[:2]:
        check_artifact(f"{name} image {image}", image_files(image, files), files, "/app")
    notices = {path: files[path] for path in REQUIRED}
    check_artifact(f"ssr image {args.ssr_image}", image_files(args.ssr_image, notices, "/app"), notices, "/app")
    check_source_archive(args.source_archive, files, manifest)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        raise SystemExit(f"release notice verification failed: {error}") from error
