<script lang="ts">
  import type { Snippet } from "svelte";
  import type { HTMLAnchorAttributes } from "svelte/elements";

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
    ...restProps
  }: {
    href?: string;
    method?: string;
    data?: Record<string, unknown>;
    replace?: boolean;
    preserveScroll?: boolean;
    preserveState?: boolean | null;
    only?: string[];
    headers?: Record<string, string>;
    children?: Snippet;
  } & HTMLAnchorAttributes = $props();
</script>

<!-- eslint-disable-next-line svelte/no-navigation-without-resolve -->
<a
  {href}
  data-method={method}
  data-replace={replace}
  data-preserve-scroll={preserveScroll}
  data-preserve-state={preserveState}
  data-only={only.join(",")}
  data-headers={JSON.stringify(headers)}
  data-payload={JSON.stringify(data)}
  {...restProps}
>
  {@render children?.()}
</a>
