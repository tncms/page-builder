<script setup lang="ts">
// Multi-line text: commits on blur only (Enter inserts a newline). Escape
// reverts. Never commits while typing.
defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: string]; commit: []; revert: [] }>()
</script>

<template>
  <textarea
    class="pb-inspector-input pb-inspector-textarea"
    rows="3"
    :value="(modelValue as string) ?? ''"
    @input="emit('update', ($event.target as HTMLTextAreaElement).value)"
    @blur="emit('commit')"
    @keydown.esc.prevent="emit('revert')"
  />
</template>
