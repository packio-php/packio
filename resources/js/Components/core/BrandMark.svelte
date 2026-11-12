<script lang="ts">
  interface Props {
    /** Keeps gradient ids unique when two shells' logos share a document. */
    idPrefix?: string;
    className?: string;
    svgClassName?: string;
  }

  let {
    idPrefix = "app",
    className = "size-10 rounded-xl",
    svgClassName = "size-10",
  }: Props = $props();
</script>

<div
  class={className}
  style="box-shadow: 0 12px 32px color-mix(in srgb, var(--accent) 38%, transparent);"
>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 64 64"
    role="img"
    aria-label="Packio logo"
    class={svgClassName}
  >
    <defs>
      <linearGradient
        id={`bg-${idPrefix}`}
        x1="12"
        y1="8"
        x2="52"
        y2="56"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0" stop-color="var(--accent)" />
        <stop offset="0.55" stop-color="var(--accent-ink)" />
        <stop offset="1" stop-color="#2f3f74" />
      </linearGradient>
      <linearGradient
        id={`glow-${idPrefix}`}
        x1="20"
        y1="18"
        x2="46"
        y2="44"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0" stop-color="#ffffff" stop-opacity="0.98" />
        <stop offset="1" stop-color="var(--accent-soft)" stop-opacity="0.92" />
      </linearGradient>
    </defs>
    <rect
      x="4"
      y="4"
      width="56"
      height="56"
      rx="16"
      fill={`url(#bg-${idPrefix})`}
    />
    <path
      d="M17 20.5 32 14l15 6.5-15 7.25L17 20.5Z"
      fill={`url(#glow-${idPrefix})`}
      opacity="0.96"
    />
    <path
      d="M17 20.5v19L32 50V27.75L17 20.5Zm30 0v19L32 50V27.75l15-7.25Z"
      fill="#ffffff"
      opacity="0.22"
    />
    <path
      d="M17 20.5 32 14l15 6.5-15 7.25L17 20.5Z"
      fill="none"
      stroke="#ffffff"
      stroke-linejoin="round"
      stroke-width="3"
    />
    <path
      d="M17 20.5v19L32 50l15-10.5v-19"
      fill="none"
      stroke="#ffffff"
      stroke-linecap="round"
      stroke-linejoin="round"
      stroke-width="3"
    />
    <path
      d="M32 27.75V50"
      fill="none"
      stroke="#ffffff"
      stroke-linecap="round"
      stroke-width="3"
    />
    <path
      d="M26 17.2 40.5 23.5"
      fill="none"
      stroke="var(--accent-ink)"
      stroke-linecap="round"
      stroke-width="2.5"
    />
    <circle
      cx="47.5"
      cy="17.5"
      r="4.5"
      fill="#facc15"
      stroke="#ffffff"
      stroke-width="2"
    />
  </svg>
</div>
