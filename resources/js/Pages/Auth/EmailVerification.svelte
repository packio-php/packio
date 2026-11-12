<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import { onDestroy, onMount } from "svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import HoneypotField from "@/Components/HoneypotField.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import Turnstile from "@/Components/Turnstile.svelte";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AuthPayload } from "@/types/auth";
  import type { ValidationErrors } from "@/types/pages";

  type TurnstileConfig = {
    enabled: boolean;
    site_key: string;
  };

  type HoneypotConfig = {
    field_name: string;
  };

  interface Props {
    errors?: ValidationErrors;
    old?: { code?: string };
    auth?: AuthPayload;
    turnstile?: TurnstileConfig | undefined;
    honeypot?: HoneypotConfig | undefined;
    resend_available_in?: number;
  }

  let {
    errors = {},
    old = {},
    auth = {},
    turnstile = undefined,
    honeypot = undefined,
    resend_available_in = 0,
  }: Props = $props();

  let code = $derived(old?.code ?? "");
  let isSubmitting = $state(false);
  let isResending = $state(false);
  let resendDisabled = $state(false);
  let resendCountdown = $state(0);
  let honeypotValue = $state("");
  let turnstileToken = $state("");
  let turnstileRef:
    | {
        reset: () => void;
      }
    | undefined = $state();

  let countdownInterval: ReturnType<typeof setInterval> | null = null;

  let isTurnstileEnabled = $derived(
    Boolean(turnstile?.["enabled"] && turnstile?.["site_key"]),
  );
  let honeypotFieldName = $derived(honeypot?.["field_name"] ?? "website_url");
  let hasCodeError = $derived(hasFieldErrors(errors, "code"));

  onMount(() => {
    if (resend_available_in > 0) {
      startResendCountdown(resend_available_in);
    }
  });

  onDestroy(() => {
    clearResendCountdown();
  });

  function clearResendCountdown(): void {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
  }

  function resetTurnstile(): void {
    turnstileRef?.reset();
  }

  function buildPayload(data: Record<string, string>): Record<string, string> {
    return {
      ...data,
      [honeypotFieldName]: honeypotValue,
      ...(isTurnstileEnabled
        ? { "cf-turnstile-response": turnstileToken }
        : {}),
    };
  }

  function startResendCountdown(seconds: number): void {
    clearResendCountdown();

    resendCountdown = Math.max(0, seconds);
    resendDisabled = true;

    if (resendCountdown <= 0) {
      resendDisabled = false;

      return;
    }

    countdownInterval = setInterval(() => {
      resendCountdown--;
      if (resendCountdown <= 0) {
        resendDisabled = false;
        clearResendCountdown();
      }
    }, 1000);
  }

  function submit(): void {
    if (isSubmitting || code.length !== 6) return;
    if (isTurnstileEnabled && !turnstileToken) return;

    isSubmitting = true;

    router.post("/auth/verification/verify", buildPayload({ code }), {
      preserveScroll: true,
      onFinish: () => {
        isSubmitting = false;
        resetTurnstile();
      },
    });
  }

  function resend(): void {
    if (resendDisabled || isResending) return;
    if (isTurnstileEnabled && !turnstileToken) return;

    isResending = true;

    router.post("/auth/verification/resend", buildPayload({}), {
      preserveScroll: true,
      onFinish: () => {
        isResending = false;
        resetTurnstile();
      },
    });
  }
</script>

<svelte:head>
  <title>Verify email</title>
</svelte:head>

<AppShell title="Verify email" {auth} flashClassName="mx-auto w-full max-w-md">
  <section class="mx-auto grid w-full max-w-lg gap-6">
    <Card className="w-full rounded-[1.75rem] p-8">
      <SectionHeading
        eyebrow="Email Verification"
        title="Verify your email"
        description="If registration can be completed, a 6-digit code will arrive by email. If you already have an account, log in or reset your password."
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
          <span class="text-sm font-medium ui-title">Verification code</span>
          <Input
            bind:value={code}
            invalid={hasCodeError}
            className="text-lg text-center tracking-widest"
            name="code"
            type="text"
            inputmode="numeric"
            maxlength={6}
            pattern="[0-9]*"
            placeholder="000000"
          />
          <FieldErrors {errors} field="code" />
        </label>

        <HoneypotField bind:value={honeypotValue} name={honeypotFieldName} />

        {#if isTurnstileEnabled}
          <Turnstile
            bind:this={turnstileRef}
            bind:token={turnstileToken}
            siteKey={turnstile?.site_key ?? ""}
            action="verify"
          />
          <FieldErrors {errors} field="turnstile" />
        {/if}

        <Button
          className="w-fit rounded-full px-5"
          type="submit"
          variant="primary"
          disabled={isSubmitting ||
            isResending ||
            code.length !== 6 ||
            (isTurnstileEnabled && !turnstileToken)}
        >
          Verify
        </Button>
      </form>

      <div class="mt-6 text-sm ui-muted">
        {#if resendDisabled}
          Resend code in {resendCountdown} seconds
        {:else}
          <button
            type="button"
            class="ui-link-accent"
            disabled={isSubmitting ||
              isResending ||
              (isTurnstileEnabled && !turnstileToken)}
            onclick={resend}
          >
            Resend code
          </button>
        {/if}
      </div>
    </Card>
  </section>
</AppShell>
