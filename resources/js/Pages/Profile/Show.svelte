<script lang="ts">
  import AppShell from "@/Layouts/AppShell.svelte";
  import Badge from "@/Components/ui/Badge.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import { formatDate } from "@/lib/date";
  import type { AuthPayload } from "@/types/auth";
  import type {
    ApiAccessTokenPayload,
    ProfileOAuthAccountPayload,
    ProfilePayload,
    SignInHistoryEntry,
  } from "@/types/pages";
  import ApiTokensSection from "./Partials/ApiTokensSection.svelte";
  import SignInHistorySection from "./Partials/SignInHistorySection.svelte";
  import SignInMethodsSection from "./Partials/SignInMethodsSection.svelte";

  interface Props {
    auth?: AuthPayload;
    profile?: ProfilePayload | null;
    oauth_accounts?: ProfileOAuthAccountPayload[];
    api_tokens?: ApiAccessTokenPayload[];
    sign_in_history?: SignInHistoryEntry[];
  }

  let {
    auth = {},
    profile = null,
    oauth_accounts = [],
    api_tokens = [],
    sign_in_history = [],
  }: Props = $props();
</script>

<svelte:head>
  <title>Profile</title>
</svelte:head>

<AppShell title="Profile" {auth}>
  <section class="grid gap-6">
    <h2 class="text-3xl font-semibold" style="color: var(--page-ink);">
      Profile
    </h2>

    <div class="grid items-start gap-6 xl:grid-cols-2">
      <Card className="rounded-[1.75rem] p-6">
        <section aria-label="Account">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <div class="min-w-0 break-words text-xl font-semibold ui-title">
              {profile?.display_name ?? ""}
            </div>
            {#each profile?.roles ?? [] as role (role)}
              <Badge tone={role === "admin" ? "danger" : "accent"}>
                {role}
              </Badge>
            {/each}
          </div>
          <div class="mt-1 break-words text-[15px] ui-muted">
            {profile?.email ?? ""}
          </div>
          <p class="mt-4 text-sm ui-muted">
            Created {formatDate(profile?.created_at)}
          </p>
        </section>
        <hr class="my-8 theme-border" />
        <SignInMethodsSection {profile} {oauth_accounts} />
      </Card>

      <ApiTokensSection {profile} {api_tokens} />
    </div>

    <SignInHistorySection {sign_in_history} />
  </section>
</AppShell>
