<script setup lang="ts">
// Boolean toggle. Commits immediately (no debounce needed for a discrete flip).
defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: boolean]; commit: [] }>()

function onChange(e: Event) {
  emit('update', (e.target as HTMLInputElement).checked)
  emit('commit')
}
</script>

<template>
  <label class="pb-inspector-toggle">
    <input type="checkbox" :checked="Boolean(modelValue)" @change="onChange" />
    <span class="pb-inspector-toggle__track" />
  </label>
</template>
