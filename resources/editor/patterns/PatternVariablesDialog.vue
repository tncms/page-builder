<script setup lang="ts">
// Phase 25.1 — the pattern variables dialog. Renders one field PER declared
// variable, driven entirely by manifest metadata (no hardcoded pattern-specific
// fields — §6). A live preview panel reflects the current values as you type (§7);
// it never touches BuilderState or the DB. On Insert it emits the collected values;
// the store resolves them against the payload template server-side.
import { computed, reactive, ref } from 'vue'
import type { PatternManifest, PatternVariable, PatternVariableValues } from './types'
import {
  initialVariableValues,
  resolveVariableValues,
  validateVariableValues,
} from './resolveVariables'

const props = defineProps<{ pattern: PatternManifest; inserting: boolean }>()
const emit = defineEmits<{ close: []; insert: [values: PatternVariableValues] }>()

const variables = computed<PatternVariable[]>(() => props.pattern.variables ?? [])
const values = reactive<PatternVariableValues>(initialVariableValues(variables.value))
const showPreview = ref(true)
const attempted = ref(false)

const errors = computed(() => validateVariableValues(variables.value, values))
const resolved = computed(() => resolveVariableValues(variables.value, values))

function setValue(id: string, value: unknown): void {
  values[id] = value
}

function onInsert(): void {
  attempted.value = true
  if (Object.keys(errors.value).length === 0) emit('insert', { ...resolved.value })
}

function isImageUrl(value: unknown): boolean {
  return typeof value === 'string' && /^(https?:\/\/|\/)/.test(value)
}

function displayValue(variable: PatternVariable): string {
  const value = resolved.value[variable.id]
  if (variable.type === 'toggle') return value ? 'On' : 'Off'
  return value === '' || value === undefined || value === null ? '—' : String(value)
}
</script>

<template>
  <div
    class="pb-pattern-dialog"
    role="dialog"
    aria-modal="true"
    :aria-label="`Configure ${pattern.name}`"
    @click.self="emit('close')"
  >
    <div class="pb-pattern-dialog__box pb-pattern-vars" tabindex="-1" @keydown.esc="emit('close')">
      <button type="button" class="pb-pattern-dialog__close" aria-label="Close" @click="emit('close')">×</button>

      <header class="pb-pattern-vars__header">
        <h3 class="pb-pattern-dialog__title">{{ pattern.name }}</h3>
        <p v-if="pattern.description" class="pb-pattern-dialog__desc">{{ pattern.description }}</p>
      </header>

      <div class="pb-pattern-vars__body" :class="{ 'has-preview': showPreview }">
        <form class="pb-pattern-vars__fields" @submit.prevent="onInsert">
          <div v-for="variable in variables" :key="variable.id" class="pb-pattern-field">
            <label :for="`pb-var-${variable.id}`" class="pb-pattern-field__label">
              {{ variable.label || variable.id }}
              <span v-if="variable.required" class="pb-pattern-field__req" aria-hidden="true">*</span>
            </label>

            <textarea
              v-if="variable.type === 'textarea'"
              :id="`pb-var-${variable.id}`"
              class="pb-pattern-field__input"
              rows="3"
              :value="String(values[variable.id] ?? '')"
              @input="setValue(variable.id, ($event.target as HTMLTextAreaElement).value)"
            ></textarea>

            <select
              v-else-if="variable.type === 'select'"
              :id="`pb-var-${variable.id}`"
              class="pb-pattern-field__input"
              :value="String(values[variable.id] ?? '')"
              @change="setValue(variable.id, ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="opt in variable.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <label v-else-if="variable.type === 'toggle'" class="pb-pattern-field__toggle">
              <input
                :id="`pb-var-${variable.id}`"
                type="checkbox"
                :checked="!!values[variable.id]"
                @change="setValue(variable.id, ($event.target as HTMLInputElement).checked)"
              />
              <span>{{ values[variable.id] ? 'Enabled' : 'Disabled' }}</span>
            </label>

            <div v-else-if="variable.type === 'color'" class="pb-pattern-field__color">
              <input
                :id="`pb-var-${variable.id}`"
                type="color"
                :value="String(values[variable.id] || '#000000')"
                @input="setValue(variable.id, ($event.target as HTMLInputElement).value)"
              />
              <input
                type="text"
                class="pb-pattern-field__input"
                :value="String(values[variable.id] ?? '')"
                @input="setValue(variable.id, ($event.target as HTMLInputElement).value)"
              />
            </div>

            <input
              v-else
              :id="`pb-var-${variable.id}`"
              class="pb-pattern-field__input"
              :type="variable.type === 'number' ? 'number' : variable.type === 'url' ? 'url' : 'text'"
              :value="String(values[variable.id] ?? '')"
              @input="setValue(variable.id, ($event.target as HTMLInputElement).value)"
            />

            <p v-if="attempted && errors[variable.id]" class="pb-pattern-field__error">{{ errors[variable.id] }}</p>
          </div>

          <p v-if="variables.length === 0" class="pb-pattern-dialog__desc">This pattern has no variables.</p>
        </form>

        <aside v-if="showPreview" class="pb-pattern-vars__preview" aria-label="Live preview">
          <p class="pb-pattern-vars__preview-title">Preview</p>
          <div class="pb-pattern-vars__preview-body">
            <div v-for="variable in variables" :key="variable.id" class="pb-pattern-preview-row">
              <span class="pb-pattern-preview-key">{{ variable.label || variable.id }}</span>
              <img
                v-if="variable.type === 'image' && isImageUrl(resolved[variable.id])"
                class="pb-pattern-preview-img"
                :src="String(resolved[variable.id])"
                :alt="variable.label || variable.id"
              />
              <span
                v-else-if="variable.type === 'color'"
                class="pb-pattern-preview-swatch"
                :style="{ background: String(resolved[variable.id] || 'transparent') }"
              >{{ displayValue(variable) }}</span>
              <span v-else class="pb-pattern-preview-val">{{ displayValue(variable) }}</span>
            </div>
          </div>
        </aside>
      </div>

      <footer class="pb-pattern-vars__actions">
        <button type="button" class="pb-pattern-btn" @click="emit('close')">Cancel</button>
        <button
          type="button"
          class="pb-pattern-btn"
          :aria-pressed="showPreview"
          @click="showPreview = !showPreview"
        >
          {{ showPreview ? 'Hide preview' : 'Preview' }}
        </button>
        <button
          type="button"
          class="pb-pattern-btn pb-pattern-btn--primary"
          :disabled="inserting"
          @click="onInsert"
        >
          {{ inserting ? 'Inserting…' : 'Insert' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.pb-pattern-dialog { position: fixed; inset: 0; z-index: 2147483200; display: flex; align-items: center; justify-content: center; padding: 2rem; background: rgba(15, 23, 42, 0.55); }
.pb-pattern-dialog__box { position: relative; width: 100%; max-width: 720px; max-height: 90vh; overflow: auto; background: #fff; border-radius: 0.75rem; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.4); }
.pb-pattern-dialog__close { position: absolute; top: 0.5rem; right: 0.5rem; border: 0; background: rgba(255,255,255,0.85); border-radius: 50%; width: 1.75rem; height: 1.75rem; font-size: 1.1rem; cursor: pointer; }
.pb-pattern-dialog__title { margin: 0; font-size: 1.05rem; font-weight: 700; }
.pb-pattern-dialog__desc { margin: 0.25rem 0 0; color: #475569; font-size: 0.82rem; }
.pb-pattern-vars__header { padding: 1rem 1.15rem 0.5rem; }
.pb-pattern-vars__body { display: grid; grid-template-columns: 1fr; gap: 1rem; padding: 0.5rem 1.15rem 1rem; }
.pb-pattern-vars__body.has-preview { grid-template-columns: 1.2fr 1fr; }
.pb-pattern-vars__fields { display: flex; flex-direction: column; gap: 0.7rem; }
.pb-pattern-field { display: flex; flex-direction: column; gap: 0.25rem; }
.pb-pattern-field__label { font-size: 0.72rem; font-weight: 600; color: #334155; }
.pb-pattern-field__req { color: #dc2626; }
.pb-pattern-field__input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 0.8rem; box-sizing: border-box; }
.pb-pattern-field__toggle { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #475569; }
.pb-pattern-field__color { display: flex; gap: 0.4rem; align-items: center; }
.pb-pattern-field__color input[type="color"] { width: 2.2rem; height: 2rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; padding: 0; background: none; }
.pb-pattern-field__error { margin: 0; color: #dc2626; font-size: 0.68rem; }
.pb-pattern-vars__preview { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.6rem; background: #f8fafc; }
.pb-pattern-vars__preview-title { margin: 0 0 0.4rem; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; }
.pb-pattern-vars__preview-body { display: flex; flex-direction: column; gap: 0.45rem; }
.pb-pattern-preview-row { display: flex; flex-direction: column; gap: 0.15rem; }
.pb-pattern-preview-key { font-size: 0.64rem; color: #94a3b8; }
.pb-pattern-preview-val { font-size: 0.8rem; color: #1e293b; word-break: break-word; }
.pb-pattern-preview-img { max-width: 100%; border-radius: 0.35rem; }
.pb-pattern-preview-swatch { display: inline-flex; align-items: center; padding: 0.15rem 0.5rem; border-radius: 0.3rem; font-size: 0.7rem; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.4); border: 1px solid rgba(15,23,42,0.1); }
.pb-pattern-vars__actions { display: flex; gap: 0.5rem; padding: 0.75rem 1.15rem 1.15rem; }
.pb-pattern-btn { flex: 1; border: 1px solid #cbd5e1; border-radius: 0.4rem; background: #fff; padding: 0.5rem; font-size: 0.8rem; cursor: pointer; }
.pb-pattern-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.pb-pattern-btn:disabled { opacity: 0.6; cursor: default; }
</style>
