<script lang="ts">
  import { router } from "@inertiajs/svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import AppShell from "@/Layouts/AppShell.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import ConfirmationModal from "@/Components/ui/ConfirmationModal.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import InsetCard from "@/Components/ui/InsetCard.svelte";
  import SourceProviderInstancesSection from "./Partials/SourceProviderInstancesSection.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import { formatDate } from "@/lib/date";
  import { mergeBranding } from "@/lib/branding";
  import type { AuthPayload } from "@/types/auth";
  import type { Branding } from "@/types/branding";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    OrganizationSettingsPayload,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    auth?: AuthPayload;
    branding?: Partial<Branding>;
    organization?: OrganizationSummary | null;
    settings?: OrganizationSettingsPayload | null;
    errors?: ValidationErrors;
    old?: { name?: string; anonymous_access?: string };
    members_count?: number;
    source_provider_instances_enabled?: boolean;
  }

  let {
    auth = {},
    branding = {},
    organization = null,
    settings = null,
    errors = {},
    old = {},
    members_count = 0,
    source_provider_instances_enabled = false,
  }: Props = $props();

  let organizationDeleteFormElement: HTMLFormElement | null = $state(null);
  let organizationDeleteConfirmOpen = $state(false);
  let organizationDeleting = $state(false);
  let resolvedBranding = $derived(mergeBranding(branding ?? {}));
  let gitlabSelfHostedRedirectUri = $derived(
    `${resolvedBranding.appBaseUrl.replace(/\/+$/, "")}/auth/oauth/gitlab_self_hosted/source-callback`,
  );
  let organizationName = $derived(old?.name ?? settings?.name ?? "");
  let anonymousAccessChecked = $derived(
    (old?.anonymous_access ?? (settings?.anonymous_access ? "1" : "")) === "1",
  );

  function withOrganization(path: string): string {
    if (!organization?.slug) {
      return path;
    }

    return path === "/"
      ? `/organization/${organization.slug}`
      : `/organization/${organization.slug}${path}`;
  }

  function submit(event: Event): void {
    const form = event.currentTarget as HTMLFormElement;

    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
    });
  }

  function requestOrganizationDelete(): void {
    organizationDeleteConfirmOpen = true;
  }

  function confirmOrganizationDelete(): void {
    const form = organizationDeleteFormElement;
    if (!form || organizationDeleting) {
      return;
    }

    organizationDeleting = true;
    router.post(form.action, Object.fromEntries(new FormData(form)), {
      preserveScroll: true,
      onFinish: () => {
        organizationDeleting = false;
        organizationDeleteConfirmOpen = false;
      },
    });
  }
</script>

<svelte:head>
  <title>Organization Settings</title>
</svelte:head>

<AppShell title="Organization Settings" {auth}>
  <section
    class={source_provider_instances_enabled
      ? "grid items-start gap-6 xl:grid-cols-[0.9fr_1.1fr]"
      : "mx-auto grid w-full max-w-3xl items-start gap-6"}
  >
    <div class="grid gap-6">
      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading
          eyebrow="Organization settings"
          title={settings?.name || organization?.name || "Organization"}
          description="Manage tenant-level behavior. Organization slug remains immutable to keep package and registry URLs stable."
          className="max-w-2xl"
        />

        <form
          class="mt-6 grid gap-5"
          method="POST"
          action={withOrganization("/settings")}
          onsubmit={(event) => {
            event.preventDefault();
            submit(event);
          }}
        >
          <InsetCard className="p-5">
            <label class="grid gap-2">
              <span class="text-sm font-medium ui-title">
                Organization name
              </span>
              <Input
                name="name"
                maxlength={50}
                required
                value={organizationName}
                invalid={hasFieldErrors(errors, "name")}
              />
              <FieldErrors {errors} field="name" />
            </label>

            <label class="mt-4 grid gap-2">
              <span class="text-sm font-medium ui-title"> Route key </span>
              <Input
                className="opacity-80"
                value={settings?.route_key || organization?.slug || ""}
                readonly
              />
            </label>

            <label
              class="ui-code mt-5 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm"
            >
              <input
                type="checkbox"
                name="anonymous_access"
                value="1"
                checked={anonymousAccessChecked}
              />
              <span> Anonymous access </span>
            </label>

            <p class="mt-3 text-sm ui-muted">
              Anonymous access allows guests to install public packages without
              a registry token. When disabled, all registry endpoints require
              authentication, including packages marked public.
            </p>
          </InsetCard>

          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="primary"
          >
            Save settings
          </Button>
        </form>
      </Card>

      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading eyebrow="Status" className="mb-4" />
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Members</p>
            <p class="ui-title mt-2 text-3xl font-semibold">
              {members_count}
            </p>
          </InsetCard>
          <InsetCard className="p-4">
            <p class="ui-eyebrow">Anonymous access</p>
            <p class="ui-title mt-2 text-xl font-semibold">
              {settings?.anonymous_access ? "enabled" : "disabled"}
            </p>
          </InsetCard>
        </div>
        <div class="mt-4 grid gap-2 text-sm ui-muted">
          <p>
            <span class="font-medium ui-title">Created at:</span>
            {formatDate(settings?.created_at)}
          </p>
          <p>
            <span class="font-medium ui-title">Updated at:</span>
            {formatDate(settings?.updated_at)}
          </p>
        </div>
      </Card>

      <Card className="rounded-[1.75rem] p-6">
        <SectionHeading
          eyebrow="Danger zone"
          title="Delete organization permanently"
          description="This action removes the organization and its tenant data without recovery. Confirm with the exact organization name."
          className="max-w-xl"
        />

        <form
          bind:this={organizationDeleteFormElement}
          class="mt-6 grid gap-4"
          method="POST"
          action={withOrganization("/settings/delete")}
          onsubmit={(event) => {
            event.preventDefault();
            requestOrganizationDelete();
          }}
        >
          <label class="grid gap-2">
            <span class="text-sm font-medium ui-title">
              Confirmation name
            </span>
            <Input
              name="confirmation_name"
              placeholder={settings?.name || organization?.name || ""}
              required
            />
          </label>
          <p class="text-sm ui-muted">
            Data is removed permanently: memberships, invitations, registry
            tokens, sources, packages, releases, and webhook history for this
            organization.
          </p>
          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="danger"
          >
            Delete organization
          </Button>
        </form>
      </Card>
    </div>

    {#if source_provider_instances_enabled}
      <SourceProviderInstancesSection
        organizationSlug={organization?.slug ?? ""}
        redirectUri={gitlabSelfHostedRedirectUri}
      />
    {/if}
  </section>

  <ConfirmationModal
    open={organizationDeleteConfirmOpen}
    eyebrow="Organization"
    title="Delete organization permanently?"
    description="This removes the organization and its tenant data without recovery."
    confirmLabel="Delete organization"
    cancelLabel="Keep organization"
    confirming={organizationDeleting}
    onCancel={() => (organizationDeleteConfirmOpen = false)}
    onConfirm={confirmOrganizationDelete}
  >
    <div class="ui-inset grid gap-2 rounded-xl border p-4">
      <p class="font-medium ui-title">
        {settings?.name || organization?.name || "Organization"}
      </p>
      <p class="break-all text-xs ui-muted">
        {settings?.route_key || organization?.slug || ""}
      </p>
      <p class="text-sm ui-muted">
        The confirmation name from the form will be submitted with this request.
      </p>
    </div>
  </ConfirmationModal>
</AppShell>
