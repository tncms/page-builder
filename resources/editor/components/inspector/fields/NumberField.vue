<script setup lang="ts">
import { computed } from 'vue'
import type { SchemaField } from '../schema'

const props = defineProps<{ modelValue: unknown; field: SchemaField }>()
const emit = defineEmits<{ update: [value: number | null]; commit: []; revert: [] }>()

const min = computed(() => props.field.editor?.min as number | undefined)
const max = computed(() => props.field.editor?.max as number | undefined)
const step = computed(() => props.field.editor?.step as number | undefined)

function onInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value
  emit('update', raw === '' ? null : Number(raw))
}
</script>

<template>
  <input
    type="number"
    class="pb-inspector-input"
    :value="modelValue ?? ''"
    :min="min"
    :max="max"
    :step="step"
    @input="onInput"
    @blur="emit('commit')"
    @keydown.enter.prevent="emit('commit')"
    @keydown.esc.prevent="emit('revert')"
  />
</template>
