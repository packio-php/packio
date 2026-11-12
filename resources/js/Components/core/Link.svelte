<script lang="ts">
  import { Link as InertiaLink } from "@inertiajs/svelte";
  import type { Snippet } from "svelte";
  import type { HTMLAnchorAttributes } from "svelte/elements";

  type LinkProps = {
    href?: string;
    method?: string;
    data?: Record<string, unknown>;
    replace?: boolean;
    preserveScroll?: boolean;
    preserveState?: boolean | null;
    only?: string[];
    headers?: Record<string, string>;
    children?: Snippet;
    onclick?: () => void;
  } & Omit<HTMLAnchorAttributes, "onclick">;

  let {
    href = "#",
    method = "get",
    data = {},
    replace = false,
    preserveScroll = false,
    preserveState = null,
    only = [],
    headers = {},
    children,
    onclick,
    // A small mouse movement during rapid navigation must not start native drag.
    draggable = false,
    ...restProps
  }: LinkProps = $props();
</script>

<InertiaLink
  {href}
  method={method as never}
  data={data as never}
  {replace}
  {preserveScroll}
  preserveState={preserveState ?? method.toLowerCase() !== "get"}
  {only}
  {headers}
  {onclick}
  {draggable}
  {...restProps as Record<string, never>}
>
  {@render children?.()}
</InertiaLink>
