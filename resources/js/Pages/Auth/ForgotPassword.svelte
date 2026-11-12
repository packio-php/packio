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
  import type {
    ForgotPasswordPageProps,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: ForgotPasswordPageProps["old"];
    auth?: AuthPayload;
    branding?: ForgotPasswordPageProps["branding"];
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
    turnstile = undefined,
    honeypot = undefined,
  }: Props = $props();

  let resolvedBranding = $derived(
    mergeBranding(branding ?? {}) as { name: string },
  );

  let email = $derived(old?.email ?? "");
  let honeypotValue = $state("");
  let turnstileToken = $state("");
  let turnstileRef:
    | {
        reset: () => void;
      }
    | undefined = $state();

  let isTurnstileEnabled = $derived(
    Boolean(turnstile?.["enabled"] && turnstile?.["site_key"]),
  );
  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");

  function submit(): void {
    if (isTurnstileEnabled && !turnstileToken) {
      return;
    }

    router.post(
      "/auth/forgot-password",
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
  <title>Forgot password</title>
</svelte:head>

<AppShell
  title="Forgot password"
  {auth}
  flashClassName="mx-auto w-full max-w-lg"
>
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      <SectionHeading
        eyebrow="Account"
        title="Forgot password"
        description={`Enter your email address and we'll send you a link to reset your ${resolvedBranding.name} password.`}
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
            action="forgot-password"
          />
          <FieldErrors {errors} field="turnstile" />
        {/if}

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={isTurnstileEnabled && !turnstileToken}
        >
          Send reset link
        </Button>
      </form>

      <p class="mt-6 text-sm ui-muted">
        Remember your password?
        <Link class="ui-link-accent" href="/auth/login">Sign in</Link>
      </p>
    </Card>
  </section>
</AppShell>
