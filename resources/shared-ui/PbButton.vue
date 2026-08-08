<script setup lang="ts">
/**
 * PB-UI-1A primitive — Button.
 *
 * Presentational only: no persistence, owner, locale, or shell logic. Two
 * semantic axes — `tone` (neutral/primary/danger colour intent) and `emphasis`
 * (solid/ghost/outline fill) — compose, e.g. tone="danger" emphasis="outline".
 * Metrics/colours are overridable via the --pb-ui-btn-* component tokens so each
 * shell keeps its own look. Icon-only buttons require an accessible label.
 */
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    tone?: 'neutral' | 'primary' | 'danger';
    emphasis?: 'solid' | 'ghost' | 'outline';
    iconOnly?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit';
    label?: string;
  }>(),
  { tone: 'neutral', emphasis: 'solid', iconOnly: false, disabled: false, type: 'button', label: undefined },
);

const emit = defineEmits<{ (e: 'click', ev: MouseEvent): void }>();

const classes = computed(() => [
  'pb-ui-btn',
  props.tone !== 'neutral' ? `pb-ui-btn--${props.tone}` : null,
  props.emphasis !== 'solid' ? `pb-ui-btn--${props.emphasis}` : null,
  props.iconOnly ? 'pb-ui-btn--icon' : null,
]);

function onClick(ev: MouseEvent): void {
  if (props.disabled) return;
  emit('click', ev);
}
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled"
    :aria-label="label"
    @click="onClick"
  >
    <slot />
  </button>
</template>
