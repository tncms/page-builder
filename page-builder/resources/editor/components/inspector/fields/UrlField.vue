<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: string]; commit: []; revert: [] }>()

const value = computed(() => (props.modelValue as string) ?? '')
// Soft validation only — never blocks editing (relative URLs/anchors are fine).
const warn = computed(() => value.value !== '' && /\s/.test(value.value))
</script>

<template>
  <div>
    <input
      type="text"
      inputmode="url"
      class="pb-inspector-input"
      :value="value"
      placeholder="https://…"
      @input="emit('update', ($event.target as HTMLInputElement).value)"
      @blur="emit('commit')"
      @keydown.enter.prevent="emit('commit')"
      @keydown.esc.prevent="emit('revert')"
    />
    <p v-if="warn" class="pb-inspector-warn">URL contains spaces — this may not link correctly.</p>
  </div>
</template>
