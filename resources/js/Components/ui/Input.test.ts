import { render } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import Input from "./Input.svelte";

describe("Input", () => {
  it("stays within narrow containers", () => {
    render(Input, {
      props: {
        name: "password",
        type: "password",
      },
    });

    expect(document.querySelector('input[name="password"]')).toHaveClass(
      "min-w-0",
      "w-full",
    );
  });
});
