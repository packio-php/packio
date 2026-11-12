<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
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
  import type {
    LoginPageProps,
    ValidationErrors,
    OAuthProviderConfig,
  } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: LoginPageProps["old"];
    auth?: AuthPayload;
    branding?: LoginPageProps["branding"];
    registration_open?: boolean;
    redirect_to?: string;
    oauth_providers?: OAuthProviderConfig[];
    turnstile?:
      | {
          enabled: boolean;
          site_key: string;
        }
      | undefined;
    honeypot?:
      | {
          field_name: string;
        }
      | undefined;
  }

  let {
    errors = {},
    old = {},
    auth = {},
    branding = {},
    registration_open = true,
    redirect_to = "",
    oauth_providers = [],
    turnstile = undefined,
    honeypot = undefined,
  }: Props = $props();

  let resolvedBranding = $derived(
    mergeBranding(branding ?? {}) as { name: string },
  );

  let email = $derived(old?.email ?? "");
  let password = $state("");
  let redirectTo = $derived(redirect_to || old?.redirect_to || "");
  let honeypotValue = $state("");
  let turnstileToken = $state("");
  let turnstileRef:
    | {
        reset: () => void;
      }
    | undefined = $state();

  let enabledProviders = $derived(
    oauth_providers?.filter((p) => p.enabled) ?? [],
  );
  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");
  let isTurnstileEnabled = $derived(
    Boolean(turnstile?.["enabled"] && turnstile?.["site_key"]),
  );

  function submit(): void {
    if (isTurnstileEnabled && !turnstileToken) {
      return;
    }

    router.post(
      Endpoints.auth.login(),
      {
        email,
        password,
        redirect_to: redirectTo,
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

  function oauthLogin(provider: string): void {
    const targetUrl = `/auth/oauth/${provider}/redirect`;
    window.location.href = redirectTo
      ? `${targetUrl}?redirect_to=${encodeURIComponent(redirectTo)}`
      : targetUrl;
  }
</script>

<svelte:head>
  <title>Sign in</title>
</svelte:head>

<AppShell title="Sign in" {auth} flashClassName="mx-auto w-full max-w-lg">
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      {#if enabledProviders.length > 0}
        <div class="flex gap-2">
          {#each enabledProviders as provider}
            <button
              type="button"
              class="oauth-btn"
              title={`Sign in with ${provider.label}`}
              onclick={() => oauthLogin(provider.provider)}
            >
              <OAuthIcon provider={provider.provider} />
            </button>
          {/each}
        </div>

        <div class="relative flex items-center justify-center py-4">
          <div class="h-px flex-1 border-t border-[var(--border)]"></div>
          <span class="mx-4 text-sm ui-muted">or</span>
          <div class="h-px flex-1 border-t border-[var(--border)]"></div>
        </div>
      {/if}

      <SectionHeading
        eyebrow="Account"
        title="Sign in"
        description={`Enter your email and password to continue to ${resolvedBranding.name}.`}
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

        <label class="grid gap-2">
          <span class="text-sm font-medium ui-title">Password</span>
          <Input
            bind:value={password}
            name="password"
            type="password"
            required
            invalid={hasFieldErrors(errors, "password")}
          />
          <FieldErrors {errors} field="password" />
        </label>

        <HoneypotField bind:value={honeypotValue} name={honeypotFieldName} />

        {#if isTurnstileEnabled}
          <Turnstile
            bind:this={turnstileRef}
            bind:token={turnstileToken}
            siteKey={turnstile?.site_key ?? ""}
            action="login"
          />
          <FieldErrors {errors} field="turnstile" />
        {/if}

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={isTurnstileEnabled && !turnstileToken}
        >
          Sign in
        </Button>
      </form>

      {#if registration_open}
        <p class="mt-6 text-sm ui-muted">
          Don't have an account?
          <Link
            class="ui-link-accent"
            href={redirectTo
              ? `/auth/register?redirect_to=${encodeURIComponent(redirectTo)}`
              : "/auth/register"}
          >
            Sign up
          </Link>
        </p>
      {:else}
        <p class="mt-6 text-sm ui-muted">Registration is currently closed.</p>
      {/if}

      <p class="mt-1 text-sm ui-muted">
        Forgot your password?
        <Link class="ui-link-accent" href="/auth/forgot-password">Reset</Link>
      </p>
    </Card>
  </section>
</AppShell>

<style>
  .oauth-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--button-secondary-border);
    background-color: var(--button-secondary-bg);
    color: var(--button-secondary-text);
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .oauth-btn:hover {
    border-color: var(--button-secondary-hover);
    background-color: var(--button-secondary-hover);
  }
</style>
