<script setup lang="ts">
/**
 * PB-UI-1 primitive — Notice (inline status message).
 *
 * `danger`/`warning` announce assertively (role="alert"); informational notices
 * use role="status". `emphasis` selects the visual archetype: `subtle` (default,
 * left-accent) or `solid` (filled tinted banner). Presentational only — metrics
 * and fill are overridable via the --pb-ui-notice-* component tokens.
 */
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'info' | 'success' | 'warning' | 'danger';
    emphasis?: 'subtle' | 'solid';
  }>(),
  { variant: 'info', emphasis: 'subtle' },
);

const role = computed(() =>
  props.variant === 'danger' || props.variant === 'warning' ? 'alert' : 'status',
);
</script>

<template>
  <div
    :class="[
      'pb-ui-notice',
      `pb-ui-notice--${variant}`,
      emphasis === 'solid' ? 'pb-ui-notice--solid' : null,
    ]"
    :role="role"
  >
    <slot />
  </div>
</template>
