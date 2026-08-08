<script setup lang="ts">
// Phase 26.5 — Typography editor. Edits a typography object:
// family / size / weight / lineHeight / letterSpacing / transform / decoration.
// Free-form text for the metric fields; transform and decoration are constrained
// selects (their CSS values are a small fixed enumeration).
import { computed } from 'vue'

type Typography = Record<string, unknown>

interface Row {
  key: string
  label: string
  control: 'text' | 'select'
  options?: string[]
}

const ROWS: Row[] = [
  { key: 'family', label: 'Family', control: 'text' },
  { key: 'size', label: 'Size', control: 'text' },
  { key: 'weight', label: 'Weight', control: 'text' },
  { key: 'lineHeight', label: 'Line height', control: 'text' },
  { key: 'letterSpacing', label: 'Letter spacing', control: 'text' },
  { key: 'transform', label: 'Transform', control: 'select', options: ['none', 'uppercase', 'lowercase', 'capitalize'] },
  { key: 'decoration', label: 'Decoration', control: 'select', options: ['none', 'underline', 'line-through', 'overline'] },
]

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: Typography]; commit: []; revert: [] }>()

const current = computed<Typography>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Typography)
    : {},
)

function onInput(key: string, e: Event): void {
  emit('update', { ...current.value, [key]: (e.target as HTMLInputElement | HTMLSelectElement).value })
}
</script>

<template>
  <div class="pb-inspector-typography">
    <label v-for="row in ROWS" :key="row.key" class="pb-inspector-typography__row">
      <span>{{ row.label }}</span>
      <select
        v-if="row.control === 'select'"
        class="pb-inspector-input"
        :value="(current[row.key] as string) ?? ''"
        @change="onInput(row.key, $event); emit('commit')"
      >
        <option value="">—</option>
        <option v-for="opt in row.options" :key="opt" :value="opt">{{ opt }}</option>
      </select>
      <input
        v-else
        type="text"
        class="pb-inspector-input"
        :value="(current[row.key] as string) ?? ''"
        @input="onInput(row.key, $event)"
        @blur="emit('commit')"
        @keydown.enter.prevent="emit('commit')"
        @keydown.esc.prevent="emit('revert')"
      />
    </label>
  </div>
</template>
