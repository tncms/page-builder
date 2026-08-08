<script setup lang="ts">
import { computed } from 'vue'
import { normalizeOptions, type SchemaField } from '../schema'

const props = defineProps<{ modelValue: unknown; field: SchemaField }>()
const emit = defineEmits<{ update: [value: string]; commit: [] }>()

const options = computed(() => normalizeOptions(props.field.options))

function onChange(e: Event) {
  emit('update', (e.target as HTMLSelectElement).value)
  emit('commit')
}
</script>

<template>
  <select class="pb-inspector-input" :value="(modelValue as string) ?? ''" @change="onChange">
    <option value="" disabled>Select…</option>
    <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
  </select>
</template>
