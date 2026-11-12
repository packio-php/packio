<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Turnstile from "@/Components/Turnstile.svelte";
  import HoneypotField from "@/Components/HoneypotField.svelte";
  import { mergeBranding } from "@/lib/branding";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AuthPayload } from "@/types/auth";
  import type { ResetPasswordPageProps, ValidationErrors } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: ResetPasswordPageProps["old"];
    auth?: AuthPayload;
    branding?: ResetPasswordPageProps["branding"];
    selector?: string;
    token?: string;
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
    selector = "",
    token = "",
    turnstile = undefined,
    honeypot = undefined,
  }: Props = $props();

  let resolvedBranding = $derived(
    mergeBranding(branding ?? {}) as { name: string },
  );

  let password = $state("");
  let passwordConfirmation = $state("");
  let honeypotValue = $state("");
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

  function submit(): void {
    if (isTurnstileEnabled && !turnstileToken) {
      return;
    }

    router.post(
      "/auth/reset-password",
      {
        selector: resolvedSelector,
        token: resolvedToken,
        password,
        password_confirmation: passwordConfirmation,
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
  let resolvedSelector = $derived(selector || old?.selector || "");
  let resolvedToken = $derived(token || old?.token || "");
  let isTurnstileEnabled = $derived(hasTurnstile(turnstile));
  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");
</script>

<svelte:head>
  <title>Reset password</title>
</svelte:head>

<AppShell
  title="Reset password"
  {auth}
  flashClassName="mx-auto w-full max-w-lg"
>
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      <SectionHeading
        eyebrow="Account"
        title="Reset password"
        description={`Enter your new ${resolvedBranding.name} password.`}
        className="max-w-lg"
      />

      <form
        class="mt-6 grid gap-4"
        onsubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        <input type="hidden" name="selector" value={resolvedSelector} />
        <input type="hidden" name="token" value={resolvedToken} />

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
            action="reset-password"
          />
          <FieldErrors {errors} field="turnstile" />
        {/if}

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={isTurnstileEnabled && !turnstileToken}
        >
          Set new password
        </Button>
      </form>

      <p class="mt-6 text-sm ui-muted">
        Remember your password?
        <Link class="ui-link-accent" href="/auth/login">Sign in</Link>
      </p>
    </Card>
  </section>
</AppShell>
