<script setup lang="ts">
// Phase 22 — A single in-canvas toolbar action button. Icon + accessible label;
// an optional danger variant for Delete. Icons never carry meaning alone — every
// button has an aria-label and a matching tooltip.
defineProps<{
  label: string
  icon: string
  danger?: boolean
  disabled?: boolean
}>()

defineEmits<{ (e: 'activate', event: MouseEvent): void }>()
</script>

<template>
  <button
    type="button"
    class="pb-live-toolbar__button"
    :class="{ 'pb-live-toolbar__button--danger': danger }"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
    @click="$emit('activate', $event)"
  >
    <span class="pb-live-toolbar__icon" aria-hidden="true">{{ icon }}</span>
  </button>
</template>
