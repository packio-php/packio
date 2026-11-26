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
    <h1 class="text-3xl font-semibold" style="color: var(--page-ink);">
      Profile
    </h1>

    <Card className="rounded-[1.75rem] p-5">
      <div class="min-w-0">
        <div class="break-words text-xl font-semibold ui-title">
          {profile?.display_name ?? ""}
        </div>
        <div class="break-words text-[15px] ui-muted">
          {profile?.email ?? ""}
        </div>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        {#each profile?.roles ?? [] as role (role)}
          <Badge tone={role === "admin" ? "danger" : "accent"}>
            {role}
          </Badge>
        {/each}
      </div>
      <p class="mt-3 text-sm ui-muted">
        Created {formatDate(profile?.created_at)}
      </p>
    </Card>

    <nav
      aria-label="Profile sections"
      class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium"
    >
      <a href="#api-tokens" class="underline-offset-4 hover:underline">
        API tokens
      </a>
      <a href="#sign-in-methods" class="underline-offset-4 hover:underline">
        Sign-in methods
      </a>
      <a href="#sign-in-history" class="underline-offset-4 hover:underline">
        Sign-in history
      </a>
    </nav>

    <ApiTokensSection {profile} {api_tokens} />
    <SignInMethodsSection {profile} {oauth_accounts} />
    <SignInHistorySection {sign_in_history} />
  </section>
</AppShell>
