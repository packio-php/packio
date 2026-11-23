import { render } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import { setPageProps } from "@/test/inertia";
import LegalNotice from "./LegalNotice.svelte";

describe("LegalNotice", () => {
  it("links both documents", () => {
    setPageProps({
      legal: { privacy_url: "/privacy", terms_url: "/terms" },
    });
    const { container } = render(LegalNotice);

    expect(container.textContent?.replace(/\s+/g, " ").trim()).toBe(
      "By creating an account you agree to the Terms of Service and acknowledge the Privacy Policy.",
    );
    expect(container.querySelector('a[href="/terms"]')).not.toBeNull();
    expect(container.querySelector('a[href="/privacy"]')).not.toBeNull();
  });

  it("mentions only terms when privacy is off", () => {
    setPageProps({ legal: { privacy_url: null, terms_url: "/terms" } });
    const { container } = render(LegalNotice);

    expect(container.textContent?.replace(/\s+/g, " ").trim()).toBe(
      "By creating an account you agree to the Terms of Service.",
    );
    expect(container.querySelector('a[href="/privacy"]')).toBeNull();
  });

  it("mentions only privacy when terms are off", () => {
    setPageProps({ legal: { privacy_url: "/privacy", terms_url: null } });
    const { container } = render(LegalNotice);

    expect(container.textContent?.replace(/\s+/g, " ").trim()).toBe(
      "By creating an account you acknowledge the Privacy Policy.",
    );
    expect(container.querySelector('a[href="/terms"]')).toBeNull();
  });

  it("renders nothing when both documents are off", () => {
    setPageProps({ legal: { privacy_url: null, terms_url: null } });
    const { container } = render(LegalNotice);

    expect(container.textContent?.trim()).toBe("");
  });
});
