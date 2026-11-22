import { spawnSync } from "node:child_process";

// shortcut: advisories without a patched release are skipped; drop each entry once a fix ships and is installed.
const ignored = new Map();
const failingSeverities = new Set(["high", "critical"]);

const audit = spawnSync("npm", ["audit", "--json"], { encoding: "utf8" });
let report;

try {
  report = JSON.parse(audit.stdout);
} catch {
  console.error(audit.stdout, audit.stderr);
  console.error("npm audit did not return JSON.");
  process.exit(1);
}

if (report.error) {
  console.error(report.error);
  process.exit(1);
}

const failing = new Map();
const skipped = new Set();

for (const vulnerability of Object.values(report.vulnerabilities ?? {})) {
  for (const advisory of vulnerability.via) {
    // String entries point at another vulnerable package, whose own advisories are checked there.
    if (
      typeof advisory === "string" ||
      !failingSeverities.has(advisory.severity)
    ) {
      continue;
    }

    const id = advisory.url.split("/").pop();

    if (ignored.has(id)) {
      skipped.add(id);
    } else {
      failing.set(
        id,
        `${advisory.name}: ${advisory.title} (${advisory.severity}) ${advisory.url}`,
      );
    }
  }
}

for (const id of skipped) {
  console.warn(`Ignored ${id}: ${ignored.get(id)}`);
}

for (const id of ignored.keys()) {
  if (!skipped.has(id)) {
    console.warn(
      `${id} is no longer reported; remove it from tools/npm-audit.mjs.`,
    );
  }
}

if (failing.size > 0) {
  console.error([...failing.values()].join("\n"));
  process.exit(1);
}

console.log(
  "npm audit: no high or critical advisories outside the ignore list.",
);
