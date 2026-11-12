#!/usr/bin/env python3
"""Download the pinned corresponding-source files listed in third-party-sources.json."""

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import tempfile
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]
CHUNK_SIZE = 1024 * 1024


def load_manifest(path):
    manifest = json.loads(path.read_text())
    if manifest.get("schema") != 1 or not isinstance(manifest.get("sources"), list):
        raise ValueError("unsupported source manifest")
    sources, names = [], set()
    for item in manifest["sources"]:
        relative = PurePosixPath(item.get("path", ""))
        url, digest, size = item.get("url"), item.get("sha256"), item.get("size")
        if (relative.parts[:1] != ("third-party-sources",) or len(relative.parts) != 2
                or relative.name in names or not isinstance(url, str) or not url.startswith("https://")
                or not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest)
                or type(size) is not int or size < 0):
            raise ValueError(f"invalid or duplicate source entry: {item!r}")
        names.add(relative.name)
        sources.append((item, relative.name))
    if not sources:
        raise ValueError("source manifest is empty")
    return sources


def verify(path, item):
    if path.is_symlink() or not path.is_file():
        raise ValueError(f"refusing non-regular source path: {path}")
    digest = hashlib.sha256()
    size = 0
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(CHUNK_SIZE), b""):
            digest.update(chunk)
            size += len(chunk)
    if size != item["size"] or digest.hexdigest() != item["sha256"]:
        raise ValueError(f"existing source file has the wrong size or SHA-256: {path}")


def download(item, destination):
    digest = hashlib.sha256()
    size = 0
    with tempfile.NamedTemporaryFile(dir=destination.parent, prefix=".notice-source-", delete=False) as temp:
        temporary_path = Path(temp.name)
        try:
            with urlopen(item["url"], timeout=60) as response:
                while chunk := response.read(CHUNK_SIZE):
                    size += len(chunk)
                    if size > item["size"]:
                        raise ValueError(f"download exceeds pinned size: {item['path']}")
                    digest.update(chunk)
                    temp.write(chunk)
            if size != item["size"] or digest.hexdigest() != item["sha256"]:
                raise ValueError(f"download has the wrong size or SHA-256: {item['path']}")
            temp.flush()
            os.fchmod(temp.fileno(), 0o644)
            try:
                os.link(temporary_path, destination)
            except FileExistsError:
                verify(destination, item)
                return "already verified"
            return "downloaded and verified"
        finally:
            temporary_path.unlink(missing_ok=True)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--manifest", type=Path, default=ROOT / "third-party-sources.json")
    parser.add_argument("--output-dir", type=Path, required=True,
                        help="directory for verified payloads, normally third-party-sources/")
    args = parser.parse_args(argv)
    sources = load_manifest(args.manifest)
    output = args.output_dir
    output.mkdir(parents=True, exist_ok=True)
    if output.is_symlink() or not output.is_dir():
        raise ValueError(f"output path must be a real directory: {output}")
    for item, name in sources:
        destination = output / name
        if destination.exists() or destination.is_symlink():
            verify(destination, item)
            result = "already verified"
        else:
            result = download(item, destination)
        print(f"{item['path']}: {result}")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        raise SystemExit(f"notice source preparation failed: {error}") from error
