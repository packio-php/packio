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
    RegisterPageProps,
    ValidationErrors,
    OAuthProviderConfig,
  } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: RegisterPageProps["old"];
    auth?: AuthPayload;
    branding?: RegisterPageProps["branding"];
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
    mergeBranding(branding ?? {}) as {
      name: string;
    },
  );

  let displayName = $state("");
  let email = $state("");
  let password = $state("");
  let passwordConfirmation = $state("");
  let redirectTo = $derived(redirect_to || old?.redirect_to || "");
  let honeypotValue = $state("");
  let submitting = $state(false);
  let turnstileToken = $state("");
  let turnstileRef:
    | {
        reset: () => void;
      }
    | undefined = $state();
  function hasTurnstile(
    payload:
      | {
          enabled: boolean;
          site_key: string;
        }
      | undefined,
  ): boolean {
    return Boolean(payload?.enabled && payload?.site_key);
  }

  let enabledProviders = $derived(
    oauth_providers?.filter((p) => p.enabled) ?? [],
  );
  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");
  let isTurnstileEnabled = $derived(hasTurnstile(turnstile));
  let canSubmit = $derived(
    registration_open &&
      !submitting &&
      (!isTurnstileEnabled || !!turnstileToken),
  );

  $effect(() => {
    displayName = old?.display_name ?? "";
    email = old?.email ?? "";
  });

  function submit(): void {
    if (submitting) {
      return;
    }

    if (!canSubmit) {
      return;
    }

    submitting = true;

    router.post(
      Endpoints.auth.register(),
      {
        display_name: displayName,
        email,
        password,
        password_confirmation: passwordConfirmation,
        redirect_to: redirectTo,
        [honeypotFieldName]: honeypotValue,
        ...(isTurnstileEnabled
          ? { "cf-turnstile-response": turnstileToken }
          : {}),
      },
      {
        preserveScroll: true,
        onFinish: () => {
          submitting = false;
          turnstileRef?.reset();
        },
      },
    );
  }

  function oauthRegister(provider: string): void {
    const targetUrl = `/auth/oauth/${provider}/redirect`;
    window.location.href = redirectTo
      ? `${targetUrl}?redirect_to=${encodeURIComponent(redirectTo)}`
      : targetUrl;
  }
</script>

<svelte:head>
  <title>Register</title>
</svelte:head>

<AppShell title="Register" {auth} flashClassName="mx-auto w-full max-w-lg">
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      {#if registration_open && enabledProviders.length > 0}
        <div class="flex gap-2">
          {#each enabledProviders as provider}
            <button
              type="button"
              class="oauth-btn"
              title={`Sign up with ${provider.label}`}
              onclick={() => oauthRegister(provider.provider)}
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
        title="Create account"
        description={registration_open
          ? `Create your ${resolvedBranding.name} account to continue.`
          : "Registration is currently closed."}
        className="max-w-lg"
      />

      {#if registration_open}
        <form
          class="mt-6 grid gap-4"
          onsubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Display name</span>
            <Input bind:value={displayName} name="display_name" required />
            <FieldErrors {errors} field="display_name" />
          </label>

          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Email</span>
            <Input
              bind:value={email}
              name="email"
              type="email"
              required
              invalid={hasFieldErrors(errors, "email")}
            />
            <div class="email-feedback" aria-live="polite">
              <FieldErrors {errors} field="email" />
            </div>
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
            <span class="text-xs ui-muted">
              Min 8 characters, at least one digit and one special character
            </span>
            <FieldErrors {errors} field="password" />
          </label>

          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">Confirm password</span>
            <Input
              bind:value={passwordConfirmation}
              name="password_confirmation"
              type="password"
              required
              invalid={hasFieldErrors(errors, "password_confirmation")}
            />
            <FieldErrors {errors} field="password_confirmation" />
          </label>

          <HoneypotField bind:value={honeypotValue} name={honeypotFieldName} />

          {#if isTurnstileEnabled}
            <Turnstile
              bind:this={turnstileRef}
              bind:token={turnstileToken}
              siteKey={turnstile?.site_key ?? ""}
              action="register"
            />
            <FieldErrors {errors} field="turnstile" />
          {/if}

          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="primary"
            disabled={!canSubmit}
          >
            Create account
          </Button>
        </form>
      {/if}

      <p class="mt-6 text-sm ui-muted">
        Already have an account?
        <Link
          class="ui-link-accent"
          href={redirectTo
            ? `/auth/login?redirect_to=${encodeURIComponent(redirectTo)}`
            : "/auth/login"}
        >
          Log in
        </Link>
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

  .email-feedback {
    min-height: 1.25rem;
  }
</style>
