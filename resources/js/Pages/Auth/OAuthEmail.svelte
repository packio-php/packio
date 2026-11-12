<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import OAuthIcon from "@/Components/ui/OAuthIcon.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Turnstile from "@/Components/Turnstile.svelte";
  import HoneypotField from "@/Components/HoneypotField.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import { Endpoints } from "@/lib/api";
  import type { AuthPayload } from "@/types/auth";
  import type { OAuthEmailPageProps, ValidationErrors } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: OAuthEmailPageProps["old"];
    auth?: AuthPayload;
    branding?: OAuthEmailPageProps["branding"];
    provider?: OAuthEmailPageProps["provider"];
    provider_label?: string;
    provider_username?: string | null;
    turnstile?: OAuthEmailPageProps["turnstile"];
    honeypot?: OAuthEmailPageProps["honeypot"];
  }

  let {
    errors = {},
    old = {},
    auth = {},
    branding = {},
    provider = "github",
    provider_label = "OAuth provider",
    provider_username = null,
    turnstile = undefined,
    honeypot = undefined,
  }: Props = $props();

  let resolvedBranding = $derived(
    mergeBranding(branding ?? {}) as { name: string },
  );
  let resolvedProvider = $derived(provider ?? "github");

  let email = $derived(old?.email ?? "");
  let honeypotValue = $state("");
  let turnstileToken = $state("");
  let turnstileRef:
    | {
        reset: () => void;
      }
    | undefined = $state();

  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");
  let isTurnstileEnabled = $derived(
    Boolean(turnstile?.["enabled"] && turnstile?.["site_key"]),
  );

  function submit(): void {
    if (isTurnstileEnabled && !turnstileToken) {
      return;
    }

    router.post(
      Endpoints.auth.oauthEmail(),
      {
        email,
        [honeypotFieldName]: honeypotValue,
        ...(isTurnstileEnabled
          ? { "cf-turnstile-response": turnstileToken }
          : {}),
      },
      {
        preserveScroll: true,
        onFinish: () => {
          turnstileRef?.reset();
        },
      },
    );
  }
</script>

<svelte:head>
  <title>Complete sign in</title>
</svelte:head>

<AppShell
  title="Complete sign in"
  {auth}
  flashClassName="mx-auto w-full max-w-lg"
>
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      <div class="mb-5 flex items-center gap-3">
        <span class="oauth-icon">
          <OAuthIcon provider={resolvedProvider} />
        </span>
        <div class="min-w-0">
          <p class="text-sm font-medium ui-title">{provider_label}</p>
          {#if provider_username}
            <p class="truncate text-sm ui-muted">{provider_username}</p>
          {/if}
        </div>
      </div>

      <SectionHeading
        eyebrow="Email required"
        title="Complete sign in"
        description={`${provider_label} did not share an email address. Enter one to continue to ${resolvedBranding.name}.`}
        className="max-w-lg"
      />

      <form
        class="mt-6 grid gap-4"
        onsubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">Email</span>
          <Input
            bind:value={email}
            name="email"
            type="email"
            required
            invalid={hasFieldErrors(errors, "email")}
          />
          <FieldErrors {errors} field="email" />
        </label>

        <HoneypotField bind:value={honeypotValue} name={honeypotFieldName} />

        {#if isTurnstileEnabled}
          <Turnstile
            bind:this={turnstileRef}
            bind:token={turnstileToken}
            siteKey={turnstile?.site_key ?? ""}
            action="oauth_email"
          />
          <FieldErrors {errors} field="turnstile" />
        {/if}

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={isTurnstileEnabled && !turnstileToken}
        >
          Continue
        </Button>
      </form>
    </Card>
  </section>
</AppShell>

<style>
  .oauth-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--button-secondary-border);
    background-color: var(--button-secondary-bg);
    color: var(--button-secondary-text);
  }
</style>
