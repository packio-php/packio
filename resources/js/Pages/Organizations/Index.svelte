<script lang="ts">
  import { router } from "@inertiajs/svelte";

  import AppShell from "@/Layouts/AppShell.svelte";
  import Link from "@/Components/core/Link.svelte";
  import FieldErrors from "@/Components/forms/FieldErrors.svelte";
  import Button from "@/Components/ui/Button.svelte";
  import Card from "@/Components/ui/Card.svelte";
  import Input from "@/Components/ui/Input.svelte";
  import SectionHeading from "@/Components/ui/SectionHeading.svelte";
  import { hasFieldErrors } from "@/lib/validationErrors";
  import type { AuthPayload } from "@/types/auth";
  import type { OrganizationSummary } from "@/types/organization";
  import type {
    OrganizationsIndexPageProps,
    ValidationErrors,
  } from "@/types/pages";

  interface Props {
    errors?: ValidationErrors;
    old?: OrganizationsIndexPageProps["old"];
    auth?: AuthPayload;
    organizations?: OrganizationSummary[];
    selection_required?: boolean;
    has_organizations?: boolean;
  }

  let {
    errors = {},
    old = {},
    auth = {},
    organizations = [],
    selection_required = false,
    has_organizations = false,
  }: Props = $props();

  let organizationName = $derived(old?.name ?? "");

  function submit(): void {
    router.post(
      "/organizations",
      { name: organizationName },
      {
        preserveScroll: true,
      },
    );
  }
</script>

<svelte:head>
  <title
    >{has_organizations ? "Choose organization" : "Create organization"}</title
  >
</svelte:head>

<AppShell
  title={has_organizations ? "Choose organization" : "Create organization"}
  {auth}
  flashClassName="mx-auto w-full max-w-2xl"
>
  <section class="mx-auto w-full max-w-2xl">
    <Card className="rounded-[1.75rem] p-8">
      <SectionHeading
        eyebrow="Organizations"
        title={has_organizations
          ? "Choose an organization or create a new one"
          : "Create your first organization"}
        description={has_organizations
          ? "No organization is selected right now. Pick one from the list below or create a new one."
          : "You do not have any organizations yet. Create your first one to start indexing sources and publishing packages."}
        className="max-w-2xl"
      />

      {#if selection_required && has_organizations}
        <div
          class="mt-6 rounded-2xl border px-4 py-3 text-sm"
          style="border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-muted);"
        >
          Choose an organization to continue.
        </div>
      {/if}

      {#if has_organizations}
        <div class="mt-6 grid gap-3">
          {#each organizations as item (item.id)}
            <Link
              class="flex items-center justify-between rounded-[1.35rem] border px-5 py-4 no-underline transition"
              style="border-color: var(--panel-border); background-color: var(--surface-muted); color: var(--page-ink);"
              href={`/organization/${item.slug}`}
            >
              <span>
                <span class="block text-sm font-semibold">{item.name}</span>
                <span
                  class="mt-1 block text-xs uppercase tracking-[0.2em] ui-muted"
                >
                  /organization/{item.slug}
                </span>
              </span>
              <span class="text-sm ui-link-accent">Open</span>
            </Link>
          {/each}
        </div>
      {/if}

      <div
        class="mt-8 border-t pt-8"
        style="border-color: var(--panel-border);"
      >
        <SectionHeading
          eyebrow="New organization"
          title={has_organizations
            ? "Create another organization"
            : "Create your workspace"}
          description="Organization slug is created once from the initial name and remains immutable."
        />

        <form
          class="mt-6 grid gap-4"
          onsubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          <div class="grid gap-2">
            <label class="grid gap-2" for="organization-name">
              <span class="text-sm font-medium ui-title">
                Organization name
              </span>
              <Input
                bind:value={organizationName}
                id="organization-name"
                maxlength={50}
                name="name"
                placeholder="Example: Platform Team"
                required
                invalid={hasFieldErrors(errors, "name")}
              />
            </label>
            <FieldErrors {errors} field="name" />
          </div>

          <Button
            className="w-fit rounded-full px-5"
            type="submit"
            variant="primary"
          >
            Create organization
          </Button>
        </form>
      </div>
    </Card>
  </section>
</AppShell>
