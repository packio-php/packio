<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AdminShell from "@/Layouts/AdminShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import type { AuthPayload } from "@/types/auth";
  import type {
    AdminSettingsPageProps,
    RuntimeSettingDefinition,
    RuntimeSettingOverride,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    settings?: AdminSettingsPageProps["settings"];
    system?: AdminSettingsPageProps["system"];
  }

  let {
    auth = {},
    settings = {
      definitions: [],
      values: {},
    },
    system = {
      app_debug: false,
      app_production_mode: false,
      app_timezone: "UTC",
      branding: {},
      registry_base_path: "/repo",
      registry_cache_ttl: 300,
      registry_metadata_format: "p2",
    },
  }: Props = $props();

  const groupLabels: Record<string, string> = {
    api: "API",
    http: "HTTP",
    identity: "Identity",
    source_provider: "Source Providers",
    oauth_login: "OAuth Login",
    source_oauth: "Source OAuth",
  };

  const groupDescriptions: Record<string, string> = {
    api: "Controls REST API documentation and automation surface behavior.",
    http: "Controls request/response behavior shared by the web UI, API, registry, and webhooks.",
    identity: "Controls account creation behavior.",
    source_provider:
      "Controls providers shown in source connection and provider access flows.",
    oauth_login: "Controls providers shown on login and registration pages.",
    source_oauth: "Controls providers available for source connection OAuth.",
  };

  const overrideLabels: Record<RuntimeSettingOverride, string> = {
    inherit: "Inherit",
    on: "On",
    off: "Off",
  };

  let overrides: Record<string, RuntimeSettingOverride> = $state({});
  let settingsSignature = $state("");

  function selectOverride(key: string, override: RuntimeSettingOverride): void {
    overrides = {
      ...overrides,
      [key]: override,
    };
  }

  function submit(): void {
    router.post(
      "/admin/settings",
      { settings: overrides },
      {
        preserveScroll: true,
      },
    );
  }

  function statusText(definition: RuntimeSettingDefinition): string {
    const value = values[definition.key];
    if (!value) {
      return "Unknown";
    }

    return value.effective ? "Enabled" : "Disabled";
  }

  function statusBadgeClass(definition: RuntimeSettingDefinition): string {
    const value = values[definition.key];

    return value?.effective ? "ui-badge-accent" : "ui-badge-muted";
  }

  function fallbackText(definition: RuntimeSettingDefinition): string {
    const value = values[definition.key];
    if (!value) {
      return definition.config_path;
    }

    return `${value.fallback ? "on" : "off"} from ${value.fallback_source}`;
  }

  function joinUrl(base: string, path: string): string {
    const normalizedPath = path.startsWith("/") ? path : `/${path}`;
    if (!base) {
      return normalizedPath;
    }

    return `${base.replace(/\/+$/, "")}${normalizedPath}`;
  }
  let definitions = $derived(settings?.definitions ?? []);
  let values = $derived(settings?.values ?? {});
  let groupedDefinitions = $derived(
    definitions.reduce<Record<string, RuntimeSettingDefinition[]>>(
      (groups, definition) => {
        groups[definition.group] = [
          ...(groups[definition.group] ?? []),
          definition,
        ];
        return groups;
      },
      {},
    ),
  );
  let groupKeys = $derived(
    [
      "identity",
      "api",
      "http",
      "source_provider",
      "oauth_login",
      "source_oauth",
    ].filter((group) => (groupedDefinitions[group] ?? []).length > 0),
  );
  let runtimeTotals = $derived(
    definitions.reduce(
      (totals, definition) => {
        const value = values[definition.key];
        if (!value) {
          return totals;
        }

        totals.total += 1;
        totals.forced += value.override === "inherit" ? 0 : 1;
        totals.inherited += value.override === "inherit" ? 1 : 0;
        totals.enabled += value.effective ? 1 : 0;
        totals.disabled += value.effective ? 0 : 1;

        return totals;
      },
      {
        disabled: 0,
        enabled: 0,
        forced: 0,
        inherited: 0,
        total: 0,
      },
    ),
  );
  let registryEndpoint = $derived(
    joinUrl(
      system?.branding?.appBaseUrl || system?.branding?.domain || "",
      system?.registry_base_path || "/repo",
    ),
  );
  $effect(() => {
    const nextSignature = definitions
      .map(
        (definition) =>
          `${definition.key}:${values[definition.key]?.override ?? "inherit"}`,
      )
      .join("|");

    if (nextSignature !== settingsSignature) {
      const nextOverrides: Record<string, RuntimeSettingOverride> = {};
      for (const definition of definitions) {
        nextOverrides[definition.key] =
          values[definition.key]?.override ?? "inherit";
      }
      overrides = nextOverrides;
      settingsSignature = nextSignature;
    }
  });
</script>

<svelte:head>
  <title>Admin Settings</title>
</svelte:head>

<AdminShell title="Admin Settings" {auth}>
  <div
    class="admin-content-inner mx-auto w-full max-w-[80rem] px-4 py-6 sm:px-6 sm:py-8 xl:px-8 xl:py-10"
  >
    <div
      class="grid w-full gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]"
    >
      <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
        <SectionHeading
          eyebrow="Project settings"
          title="Runtime overrides"
          description="Operational settings can inherit environment defaults or be forced on and off at runtime."
        />

        <form
          class="mt-6 grid gap-5"
          onsubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          {#each groupKeys as group}
            <section class="grid gap-3">
              <div>
                <h2
                  class="text-sm font-semibold"
                  style="color: var(--page-ink);"
                >
                  {groupLabels[group] ?? group}
                </h2>
                <p class="mt-1 text-sm ui-muted">
                  {groupDescriptions[group] ?? ""}
                </p>
              </div>

              <div class="overflow-hidden rounded-lg border theme-border">
                {#each groupedDefinitions[group] ?? [] as definition}
                  <div
                    class="grid gap-3 border-b p-4 last:border-b-0 theme-border lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                    style="background-color: var(--surface-muted);"
                  >
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3
                          class="text-sm font-semibold"
                          style="color: var(--page-ink);"
                        >
                          {definition.label}
                        </h3>
                        <span
                          class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${statusBadgeClass(definition)}`}
                        >
                          {statusText(definition)}
                        </span>
                      </div>
                      {#if definition.help_text}
                        <p class="mt-1 text-sm ui-muted">
                          {definition.help_text}
                        </p>
                      {/if}
                      <p class="mt-1 text-xs ui-muted">
                        Fallback: {fallbackText(definition)}
                      </p>
                    </div>

                    <div
                      class="grid h-fit min-w-0 grid-cols-3 overflow-hidden rounded-lg border theme-border"
                      aria-label={`${definition.label} override`}
                    >
                      {#each definition.allowed_values as override}
                        <button
                          type="button"
                          class="h-10 min-w-0 cursor-pointer px-2 text-sm font-medium transition sm:min-w-20 sm:px-3"
                          class:border-l={override !==
                            definition.allowed_values[0]}
                          class:theme-border={override !==
                            definition.allowed_values[0]}
                          style={`background-color: ${
                            overrides[definition.key] === override
                              ? "var(--button-primary-bg)"
                              : "var(--button-secondary-bg)"
                          }; color: ${
                            overrides[definition.key] === override
                              ? "var(--button-primary-text)"
                              : "var(--button-secondary-text)"
                          };`}
                          aria-pressed={overrides[definition.key] === override}
                          onclick={() =>
                            selectOverride(definition.key, override)}
                        >
                          {overrideLabels[override]}
                        </button>
                      {/each}
                    </div>
                  </div>
                {/each}
              </div>
            </section>
          {/each}

          <Button
            className="w-full px-5 sm:w-fit"
            type="submit"
            variant="primary"
          >
            Save settings
          </Button>
        </form>
      </Card>

      <div class="grid gap-6">
        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading
            eyebrow="Runtime"
            title="Runtime policy summary"
            description="Effective settings combine admin overrides with environment and config fallbacks."
          />
          <div class="mt-6 grid grid-cols-2 gap-3">
            <div class="ui-inset rounded-xl p-3">
              <p class="text-xs font-medium ui-muted">Total settings</p>
              <p class="mt-1 text-2xl font-semibold ui-title">
                {runtimeTotals.total}
              </p>
            </div>
            <div class="ui-inset rounded-xl p-3">
              <p class="text-xs font-medium ui-muted">Forced overrides</p>
              <p class="mt-1 text-2xl font-semibold ui-title">
                {runtimeTotals.forced}
              </p>
            </div>
            <div class="ui-inset rounded-xl p-3">
              <p class="text-xs font-medium ui-muted">Effective enabled</p>
              <p class="mt-1 text-2xl font-semibold ui-title">
                {runtimeTotals.enabled}
              </p>
            </div>
            <div class="ui-inset rounded-xl p-3">
              <p class="text-xs font-medium ui-muted">Effective disabled</p>
              <p class="mt-1 text-2xl font-semibold ui-title">
                {runtimeTotals.disabled}
              </p>
            </div>
          </div>

          <dl class="mt-6 grid gap-3 text-sm">
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Inherited from config
              </dt>
              <dd class="mt-1 ui-muted">
                {runtimeTotals.inherited} settings
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Config cache
              </dt>
              <dd class="mt-1 ui-muted">
                Runtime changes apply immediately; config fallback changes still
                require cache clear when cache is enabled.
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Application mode
              </dt>
              <dd class="mt-1 flex flex-wrap gap-2">
                <span
                  class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                    system?.app_production_mode
                      ? "ui-badge-accent"
                      : "ui-badge-muted"
                  }`}
                >
                  {system?.app_production_mode ? "production" : "local"}
                </span>
                <span
                  class={`rounded-full px-2.5 py-1 text-[11px] font-medium ${
                    system?.app_debug ? "ui-badge-gold" : "ui-badge-muted"
                  }`}
                >
                  debug {system?.app_debug ? "on" : "off"}
                </span>
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Timezone
              </dt>
              <dd class="mt-1 ui-muted">{system?.app_timezone}</dd>
            </div>
          </dl>
        </Card>

        <Card className="rounded-xl p-4 sm:rounded-[1.75rem] sm:p-6">
          <SectionHeading
            eyebrow="Registry identity"
            title={system?.branding?.name || "Packio"}
            description={system?.branding?.tagline ||
              "Composer registry identity used by clients and tokens."}
          />
          <dl class="mt-6 grid gap-3 text-sm">
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                App URL
              </dt>
              <dd class="mt-1 break-all ui-muted">
                {system?.branding?.appBaseUrl ||
                  system?.branding?.domain ||
                  "Unavailable"}
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Registry endpoint
              </dt>
              <dd class="mt-1 break-all ui-muted">{registryEndpoint}</dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Composer alias
              </dt>
              <dd class="mt-1 ui-muted">
                {system?.branding?.composerRepositoryAlias || "Unavailable"}
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Token prefix
              </dt>
              <dd class="mt-1 ui-muted">
                {system?.branding?.registryTokenPrefix || "Unavailable"}
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Auth realm
              </dt>
              <dd class="mt-1 ui-muted">
                {system?.branding?.registryAuthRealm || "Unavailable"}
              </dd>
            </div>
            <div>
              <dt class="font-medium" style="color: var(--page-ink);">
                Metadata format
              </dt>
              <dd class="mt-1 ui-muted">
                {system?.registry_metadata_format}, cache {system?.registry_cache_ttl}s
              </dd>
            </div>
          </dl>
        </Card>
      </div>
    </div>
  </div>
</AdminShell>
