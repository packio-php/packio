<script lang="ts">
  const perPageOptions = [10, 20, 50, 100];

  let {
    currentPage,
    lastPage,
    hasMorePages,
    hasPreviousPages,
    from,
    to,
    total,
    perPage,
    onPageChange,
    onPerPageChange = null,
    showPerPageSelector = true,
  }: {
    currentPage: number;
    lastPage: number;
    hasMorePages: boolean;
    hasPreviousPages: boolean;
    from: number;
    to: number;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange?: ((perPage: number) => void) | null;
    showPerPageSelector?: boolean;
  } = $props();

  function getPageNumbers(): number[] {
    const pages: number[] = [];
    const delta = 2;

    for (let i = 1; i <= lastPage; i++) {
      if (
        i === 1 ||
        i === lastPage ||
        (i >= currentPage - delta && i <= currentPage + delta)
      ) {
        pages.push(i);
      } else if (
        i === currentPage - delta - 1 ||
        i === currentPage + delta + 1
      ) {
        pages.push(-1); // Ellipsis marker
      }
    }

    // Remove duplicate ellipses
    return pages.filter((page, index, arr) => {
      if (page === -1) {
        return arr[index - 1] !== -1;
      }
      return true;
    });
  }
</script>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <!-- Info -->
  <div class="text-sm ui-muted">
    Showing <span class="font-medium ui-title">{from}</span> to
    <span class="font-medium ui-title">{to}</span> of
    <span class="font-medium ui-title">{total}</span> results
  </div>

  {#if showPerPageSelector && onPerPageChange}
    <!-- Per page selector -->
    <div class="flex items-center gap-2">
      <span class="text-sm ui-muted">Per page:</span>
      <select
        class="cursor-pointer rounded-lg border border-(--panel-border) bg-(--surface) px-3 py-1.5 text-sm transition hover:bg-(--surface-muted)"
        value={perPage}
        onchange={(e) => onPerPageChange?.(parseInt(e.currentTarget.value, 10))}
      >
        {#each perPageOptions as option (option)}
          <option value={option}>{option}</option>
        {/each}
      </select>
    </div>
  {/if}

  <!-- Page navigation -->
  <nav class="flex items-center gap-1">
    <!-- Previous button -->
    <button
      class="cursor-pointer rounded-lg border border-(--panel-border) px-3 py-1.5 text-sm transition hover:bg-(--surface-muted) disabled:cursor-not-allowed disabled:opacity-50"
      disabled={!hasPreviousPages}
      onclick={() => onPageChange(currentPage - 1)}
    >
      ←
    </button>

    <!-- Page numbers -->
    {#each getPageNumbers() as page, index (`${page}-${index}`)}
      {#if page === -1}
        <span class="px-2 py-1.5 text-sm ui-muted">...</span>
      {:else}
        <button
          class="cursor-pointer rounded-lg px-3 py-1.5 text-sm transition"
          class:bg-(--accent-soft)={page === currentPage}
          class:text-(--accent-ink)={page === currentPage}
          class:border={page !== currentPage}
          class:border-(--panel-border)={page !== currentPage}
          class:hover:bg-(--surface-muted)={page !== currentPage}
          onclick={() => onPageChange(page)}
        >
          {page}
        </button>
      {/if}
    {/each}

    <!-- Next button -->
    <button
      class="cursor-pointer rounded-lg border border-(--panel-border) px-3 py-1.5 text-sm transition hover:bg-(--surface-muted) disabled:cursor-not-allowed disabled:opacity-50"
      disabled={!hasMorePages}
      onclick={() => onPageChange(currentPage + 1)}
    >
      →
    </button>
  </nav>
</div>
