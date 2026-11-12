import { describe, expect, it } from "vitest";
import { browserBranding, defaultBranding, mergeBranding } from "./branding";
import { formatDate } from "./date";
import { jobStatusTone, jobTypeLabel } from "./job";
import { providerIconKey } from "./providerPresentation";

describe("small frontend helpers", () => {
  it("formats dates and preserves invalid values", () => {
    expect(formatDate(null)).toBe("—");
    expect(formatDate("not-a-date")).toBe("not-a-date");
    expect(formatDate("2024-01-02T03:04:05")).toBe("2024-01-02 03:04:05");
  });

  it("maps job labels and tones", () => {
    expect(jobTypeLabel("source.sync")).toBe("Source Sync");
    expect(jobTypeLabel("identity.auth_mail")).toBe("Authentication Email");
    expect(jobTypeLabel("increment.download.count")).toBe(
      "Increment Download Count",
    );
    expect(jobTypeLabel("custom.job")).toBe("custom.job");
    expect(jobStatusTone("running")).toBe("gold");
    expect(jobStatusTone("completed")).toBe("accent");
    expect(jobStatusTone("unknown")).toBe("muted");
  });

  it("merges branding defaults and browser branding", () => {
    expect(mergeBranding({ name: "Packio" })).toEqual({
      ...defaultBranding,
      name: "Packio",
    });

    (window as Window & { __appBranding?: unknown }).__appBranding = {
      name: "Browser Packio",
    };

    expect(browserBranding().name).toBe("Browser Packio");
  });

  it("maps provider icon keys", () => {
    expect(providerIconKey("gitlab_self_hosted")).toBe("gitlab");
    expect(providerIconKey("github")).toBe("github");
  });
});
