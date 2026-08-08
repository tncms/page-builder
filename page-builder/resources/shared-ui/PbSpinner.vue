<script setup lang="ts">
/**
 * PB-UI-1A primitive — Spinner (indeterminate loading indicator).
 *
 * Presentational only. Carries an accessible label via role="status"; the
 * animation zeroes under prefers-reduced-motion through the duration tokens.
 */
withDefaults(defineProps<{ label?: string }>(), { label: 'Loading…' });
</script>

<template>
  <span class="pb-ui-spinner" role="status" :aria-label="label" />
</template>
