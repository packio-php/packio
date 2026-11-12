import { render } from "@testing-library/svelte";
import { describe, expect, it, vi } from "vitest";
import Turnstile from "./Turnstile.svelte";

type TurnstileOptions = {
  sitekey: string;
  callback: (token: string) => void;
  "error-callback": () => void;
  "expired-callback": () => void;
  theme: string;
  action: string;
  appearance: string;
};

function installTurnstile() {
  const turnstile = {
    render: vi.fn(
      (_element: HTMLElement, _options: TurnstileOptions) => "widget-1",
    ),
    remove: vi.fn(),
    reset: vi.fn(),
  };

  (window as Window & { turnstile?: typeof turnstile }).turnstile = turnstile;

  return turnstile;
}

describe("Turnstile", () => {
  it("renders a widget when turnstile is already loaded", () => {
    const turnstile = installTurnstile();
    const ontoken = vi.fn();

    render(Turnstile, {
      props: {
        siteKey: "site-key",
        action: "login",
        ontoken,
      },
    });

    expect(turnstile.render).toHaveBeenCalledOnce();
    const options = turnstile.render.mock.calls[0][1];
    expect(options).toMatchObject({
      sitekey: "site-key",
      theme: "auto",
      action: "login",
      appearance: "interaction-only",
    });

    options.callback("token-a");
    options["expired-callback"]();

    expect(ontoken).toHaveBeenNthCalledWith(1, "token-a");
    expect(ontoken).toHaveBeenNthCalledWith(2, "");
  });

  it("injects the Cloudflare script only once", () => {
    render(Turnstile, { props: { siteKey: "site-key" } });
    render(Turnstile, { props: { siteKey: "site-key" } });

    expect(
      document.head.querySelectorAll("#cloudflare-turnstile-script"),
    ).toHaveLength(1);
  });

  it("clears token when script loading fails", async () => {
    const ontoken = vi.fn();
    render(Turnstile, {
      props: {
        siteKey: "site-key",
        ontoken,
      },
    });

    document
      .getElementById("cloudflare-turnstile-script")
      ?.dispatchEvent(new Event("error"));

    expect(ontoken).toHaveBeenCalledWith("");
  });

  it("resets and removes an existing widget", () => {
    const turnstile = installTurnstile();
    const result = render(Turnstile, { props: { siteKey: "site-key" } });

    result.component.reset();
    result.unmount();

    expect(turnstile.reset).toHaveBeenCalledWith("widget-1");
    expect(turnstile.remove).toHaveBeenCalledWith("widget-1");
  });
});
