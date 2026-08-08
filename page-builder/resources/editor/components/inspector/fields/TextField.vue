<script setup lang="ts">
// A plain text input. Emits `update` on input (LOCAL only), `commit` on blur or
// Enter, and `revert` on Escape. Pure/controlled — holds no local state, so the
// input never re-renders mid-typing.
defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: string]; commit: []; revert: [] }>()
</script>

<template>
  <input
    type="text"
    class="pb-inspector-input"
    :value="(modelValue as string) ?? ''"
    @input="emit('update', ($event.target as HTMLInputElement).value)"
    @blur="emit('commit')"
    @keydown.enter.prevent="emit('commit')"
    @keydown.esc.prevent="emit('revert')"
  />
</template>
