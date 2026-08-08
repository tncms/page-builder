<script setup lang="ts">
/**
 * PB-UI-1A primitive — Badge (inline status/label pill).
 *
 * Presentational only: no persistence, owner, status, or locale logic. Semantic
 * `variant` selects colour; metrics and colours are overridable via the
 * `--pb-ui-badge-*` component tokens so a shell can preserve its own look
 * without the primitive knowing shell names or business meaning.
 */
withDefaults(
  defineProps<{ variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }>(),
  { variant: 'neutral' },
);
</script>

<template>
  <span :class="['pb-ui-badge', variant !== 'neutral' ? `pb-ui-badge--${variant}` : null]">
    <slot />
  </span>
</template>
