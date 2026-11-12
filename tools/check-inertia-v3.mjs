import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";

const sourceRoot = "resources/js";
const forbiddenEverywhere = [
  /\bpageStore\b/,
  /\binertiaClient\b/,
  /svelte\/legacy/,
];
const forbiddenSvelteOnly = [
  /\bexport\s+let\b/,
  /^\s*\$:/m,
  /\bon:[A-Za-z][\w-]*\s*=/,
  /<slot(?:\s|>|\/)/,
  /\$\$restProps/,
];

function sourceFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);

    return entry.isDirectory() ? sourceFiles(path) : [path];
  });
}

const violations = [];

for (const file of sourceFiles(sourceRoot)) {
  if (!file.endsWith(".ts") && !file.endsWith(".svelte")) {
    continue;
  }

  const content = readFileSync(file, "utf8");
  const patterns = file.endsWith(".svelte")
    ? [...forbiddenEverywhere, ...forbiddenSvelteOnly]
    : forbiddenEverywhere;

  for (const pattern of patterns) {
    if (pattern.test(content)) {
      violations.push(`${file}: ${pattern}`);
    }
  }
}

if (violations.length > 0) {
  console.error("Inertia v3 legacy compatibility check failed:");
  console.error(violations.join("\n"));
  process.exitCode = 1;
}
