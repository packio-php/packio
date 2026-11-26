<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type {
    ApiAccessTokenPayload,
    ProfileOAuthAccountPayload,
    ProfilePageProps,
    ProfilePayload,
    SignInHistoryEntry,
  } from "@/types/pages";
  import ApiTokensSection from "./Partials/ApiTokensSection.svelte";
  import SignInHistorySection from "./Partials/SignInHistorySection.svelte";
  import SignInMethodsSection from "./Partials/SignInMethodsSection.svelte";

  interface Props {
    auth?: AuthPayload;
    profile?: ProfilePayload | null;
    branding?: ProfilePageProps["branding"];
    oauth_accounts?: ProfileOAuthAccountPayload[];
    api_tokens?: ApiAccessTokenPayload[];
    sign_in_history?: SignInHistoryEntry[];
  }

  let {
    auth = {},
    profile = null,
    branding = {},
    oauth_accounts = [],
    api_tokens = [],
    sign_in_history = [],
  }: Props = $props();

  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
</script>

<svelte:head>
  <title>Profile</title>
</svelte:head>

<AppShell title="Profile" {auth}>
  <section class="grid gap-6">
    <Card className="rounded-[1.75rem] p-6">
      <SectionHeading
        eyebrow={`${resolvedBranding.name} account`}
        title={profile?.display_name ?? "Profile"}
        description={profile?.email ?? ""}
      />
      <div class="mt-4 flex flex-wrap gap-2">
        {#each profile?.roles ?? [] as role (role)}
          <Badge tone={role === "admin" ? "danger" : "accent"}>
            {role}
          </Badge>
        {/each}
      </div>
      <div class="mt-6 grid gap-3 text-sm ui-muted">
        <p>
          <span class="font-medium ui-title">Created at:</span>
          {formatDate(profile?.created_at)}
        </p>
        <p>
          <span class="font-medium ui-title">Updated at:</span>
          {formatDate(profile?.updated_at)}
        </p>
      </div>
    </Card>

    <ApiTokensSection {profile} {api_tokens} />
    <SignInMethodsSection {profile} {oauth_accounts} />
    <SignInHistorySection {sign_in_history} />
  </section>
</AppShell>
