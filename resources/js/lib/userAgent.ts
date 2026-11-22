const browsers: [string, string][] = [
  ["Edg/", "Edge"],
  ["OPR/", "Opera"],
  ["Firefox/", "Firefox"],
  ["Chrome/", "Chrome"],
  ["Safari/", "Safari"],
];

const systems: [RegExp, string][] = [
  [/Windows/, "Windows"],
  [/Android/, "Android"],
  [/iPhone|iPad/, "iOS"],
  [/Mac OS X/, "macOS"],
  [/CrOS/, "ChromeOS"],
  [/Linux/, "Linux"],
];

export function describeUserAgent(
  userAgent: string | null | undefined,
): string {
  const ua = userAgent ?? "";
  const browser = browsers.find(([token]) => ua.includes(token))?.[1];
  const system = systems.find(([pattern]) => pattern.test(ua))?.[1];

  return [browser, system].filter(Boolean).join(" on ") || "Unknown browser";
}
