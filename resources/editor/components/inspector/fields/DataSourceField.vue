<script setup lang="ts">
// PB-XREG-2 — Data-source selector. Renders ONLY from server-published schema
// metadata (`field.editor.sources` = registered, record-kind-compatible sources;
// `field.editor.strings` = Admin-UI-locale chrome). The value it emits is plain
// configuration `{ source, limit, options }` — the server re-validates it on save
// and on every render; the browser never supplies behaviour or records.
//
// A saved source that is not in the published list (its plugin is inactive) is
// shown as unavailable and left untouched: no other source is auto-selected.
import { computed } from 'vue'
import type { SchemaField } from '../schema'

interface SourceOption {
  key: string
  label: string
  type: 'enum' | 'boolean'
  choices: Array<{ value: string; label: string }>
  default: string | boolean
}

interface SourceMeta {
  id: string
  label: string
  recordKind: string
  maxLimit: number
  defaultLimit: number
  options: SourceOption[]
}

interface DataSourceValue {
  source: string
  limit?: number
  options?: Record<string, string | boolean>
}

const props = defineProps<{ modelValue: unknown; field: SchemaField }>()
const emit = defineEmits<{ update: [value: DataSourceValue]; commit: [] }>()

const sources = computed<SourceMeta[]>(() => {
  const list = props.field.editor?.sources
  return Array.isArray(list) ? (list as SourceMeta[]) : []
})
const strings = computed(() => (props.field.editor?.strings ?? {}) as Record<string, string>)
const t = (key: string, fallback: string): string => strings.value[key] || fallback

const value = computed<DataSourceValue | null>(() => {
  const v = props.modelValue
  return v && typeof v === 'object' && !Array.isArray(v) && typeof (v as DataSourceValue).source === 'string'
    ? (v as DataSourceValue)
    : null
})
const currentId = computed(() => value.value?.source ?? '')
const current = computed(() => sources.value.find((s) => s.id === currentId.value) ?? null)
const unavailable = computed(() => currentId.value !== '' && current.value === null)

const limit = computed(() => value.value?.limit ?? current.value?.defaultLimit ?? 1)

function optionValue(option: SourceOption): string | boolean {
  const saved = value.value?.options?.[option.key]
  return saved === undefined ? option.default : saved
}

function emitValue(next: DataSourceValue): void {
  emit('update', next)
  emit('commit')
}

function onSource(e: Event): void {
  const id = (e.target as HTMLSelectElement).value
  const meta = sources.value.find((s) => s.id === id)
  if (!meta) return
  // A new source starts from its own declared defaults (server fills options).
  emitValue({ source: meta.id, limit: meta.defaultLimit, options: {} })
}

function onLimit(e: Event): void {
  if (!current.value) return
  const raw = Number((e.target as HTMLInputElement).value)
  if (!Number.isFinite(raw)) return
  const bounded = Math.min(Math.max(Math.trunc(raw), 1), current.value.maxLimit)
  emitValue({ source: current.value.id, limit: bounded, options: { ...(value.value?.options ?? {}) } })
}

function onOption(option: SourceOption, next: string | boolean): void {
  if (!current.value) return
  emitValue({
    source: current.value.id,
    limit: limit.value,
    options: { ...(value.value?.options ?? {}), [option.key]: next },
  })
}
</script>

<template>
  <div class="pb-data-source" data-pb-data-source>
    <p v-if="sources.length === 0 && !unavailable" class="pb-inspector-hint" data-pb-data-source-none>
      {{ t('none', 'No compatible data sources are available.') }}
    </p>

    <template v-else>
      <select
        class="pb-inspector-input"
        :aria-label="t('source', 'Data source')"
        :value="currentId"
        data-pb-data-source-select
        @change="onSource"
      >
        <option value="" disabled>{{ t('choose', 'Choose a data source…') }}</option>
        <option v-if="unavailable" :value="currentId" disabled>{{ currentId }}</option>
        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.label }}</option>
      </select>

      <p v-if="unavailable" class="pb-inspector-warn" role="status" data-pb-data-source-unavailable>
        {{ t('unavailable', 'This data source is unavailable. Its settings are kept and will apply again when its plugin is active.') }}
      </p>

      <template v-if="current">
        <label class="pb-data-source__row">
          <span class="pb-data-source__label">{{ t('items', 'Number of items') }}</span>
          <input
            type="number"
            class="pb-inspector-input"
            min="1"
            :max="current.maxLimit"
            step="1"
            :value="limit"
            data-pb-data-source-limit
            @change="onLimit"
          />
        </label>

        <label v-for="option in current.options" :key="option.key" class="pb-data-source__row">
          <span class="pb-data-source__label">{{ option.label }}</span>
          <select
            v-if="option.type === 'enum'"
            class="pb-inspector-input"
            :value="optionValue(option) as string"
            :data-pb-data-source-option="option.key"
            @change="onOption(option, ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="c in option.choices" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <input
            v-else
            type="checkbox"
            :checked="optionValue(option) === true"
            :data-pb-data-source-option="option.key"
            @change="onOption(option, ($event.target as HTMLInputElement).checked)"
          />
        </label>
      </template>
    </template>
  </div>
</template>

<style scoped>
.pb-data-source {
  display: grid;
  gap: 0.5rem;
}
.pb-data-source__row {
  display: grid;
  gap: 0.25rem;
}
.pb-data-source__row:has(input[type='checkbox']) {
  grid-template-columns: 1fr auto;
  align-items: center;
}
.pb-data-source__label {
  font-size: 0.75rem;
  color: var(--pb-muted);
}
</style>
