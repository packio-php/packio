#!/usr/bin/env python3
"""Run the E1 final-image stack with isolated data and a redacted report."""

import argparse
import json
from pathlib import Path
import shutil
import signal
import sys
import time

from product_fixture import (FixtureCleanupError, FixtureError, prepare_dataset, public_report, report_image_ids,
                             start_fixture, stop_fixture, verify_private_install)


def parser():
    value = argparse.ArgumentParser(description=__doc__)
    value.add_argument("--app-image", required=True)
    value.add_argument("--worker-image", required=True)
    value.add_argument("--ssr-image", required=True)
    value.add_argument("--expected-sha", required=True)
    value.add_argument("--report", required=True)
    value.add_argument("--source-identity", help="R1 dirty test snapshot identity JSON")
    value.add_argument("--build-record", help="R1 dirty test snapshot build record JSON")
    return value


def main(argv=None):
    args = parser().parse_args(argv)
    if bool(args.source_identity) != bool(args.build_record):
        parser().error("--source-identity and --build-record must be supplied together")
    Path(args.report).unlink(missing_ok=True)
    started = time.monotonic()
    context = None
    result = {"candidate_sha": args.expected_sha, "images": {}, "results": {"fixture": "failed"}}
    failure = None
    cleanup_failure = None
    def interrupted(_signum, _frame):
        raise FixtureError("fixture interrupted")

    signal.signal(signal.SIGTERM, interrupted)
    try:
        snapshot = ({"identity": args.source_identity, "build": args.build_record}
                    if args.source_identity else None)
        context = start_fixture(args.app_image, args.worker_image, args.ssr_image, args.expected_sha,
                                test_snapshot=snapshot,
                                test_snapshot_kind=("disposable_local_R1_test_snapshot" if snapshot else
                                                    "disposable_local_O1_test_snapshot"))
        dataset = prepare_dataset(context)
        private_install = verify_private_install(context, dataset)
        result = {
            "candidate_sha": args.expected_sha,
            "images": report_image_ids(context),
            "fixture_sha256": context.get("fixture_sha256"),
            "results": context.get("results", {}),
            "private_install": private_install,
        }
        if (context.get("test_snapshot_proof") or {}).get("kind") == "disposable_local_R1_test_snapshot":
            result["source_snapshot"] = context["test_snapshot_proof"]
            result["image_provenance"] = context["image_report"]["images"]
    except Exception as error:
        failure = error if isinstance(error, FixtureError) else FixtureError("unexpected product fixture failure")
        cleanup_failure = getattr(error, "cleanup_diagnostic", None)
        result["results"] = {"fixture": "failed"}
    finally:
        if context is not None:
            try:
                stop_fixture(context)
            except Exception as error:
                cleanup_failure = (str(error) if isinstance(error, FixtureCleanupError)
                                   else "owned fixture cleanup failed")
                if failure is None:
                    failure = FixtureError("owned product fixture cleanup failed")
                result["results"] = {"fixture": "failed"}
            private_dir = context.get("private_dir")
            if private_dir:
                shutil.rmtree(private_dir, ignore_errors=True)
        result["duration_seconds"] = round(time.monotonic() - started, 3)
        if not isinstance(result.get("candidate_sha"), str) or len(result["candidate_sha"]) != 40 or any(
            character not in "0123456789abcdef" for character in result["candidate_sha"]
        ):
            result.pop("candidate_sha", None)
        safe = public_report(result)
        report = Path(args.report)
        report.parent.mkdir(parents=True, exist_ok=True)
        from product_fixture import _image_harness
        _image_harness().write_report(report, safe)
    if failure:
        print(f"E1/E2/E3 product fixture failed: {failure}", file=sys.stderr)
        if cleanup_failure:
            print(f"E1/E2/E3 cleanup diagnostic: {cleanup_failure}", file=sys.stderr)
        return 1
    print("E1/E2/E3 product fixture passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
