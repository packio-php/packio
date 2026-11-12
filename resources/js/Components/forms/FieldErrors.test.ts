import { render, screen } from "@testing-library/svelte";
import { describe, expect, it } from "vitest";
import FieldErrors from "./FieldErrors.svelte";

describe("FieldErrors", () => {
  it("renders normalized field messages", () => {
    render(FieldErrors, {
      props: {
        field: "displayName",
        errors: {
          display_name: ["Required"],
          displayName: ["Too short"],
        },
      },
    });

    expect(screen.getByText("Required")).toBeInTheDocument();
    expect(screen.getByText("Too short")).toBeInTheDocument();
  });

  it("renders nothing for missing messages", () => {
    const { container } = render(FieldErrors, {
      props: {
        field: "email",
        errors: {},
      },
    });

    expect(container).toHaveTextContent("");
  });
});
