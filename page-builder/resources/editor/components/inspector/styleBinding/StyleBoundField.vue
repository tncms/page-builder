<script setup lang="ts">
// Phase 26.9 — Style-bound field wrapper.
//
// Adds token binding + a value-source indicator around a pure editor (color / spacing
// / typography), so the token/style logic lives in ONE place instead of being copied
// into each editor. The editors stay unchanged and independently testable.
//
// Composition:
//   - Standalone (non-responsive) it is rendered directly by InspectorField.
//   - Responsive fields render it INSIDE ResponsivePropertyField, so it operates on the
//     active breakpoint's value — a token can be bound per breakpoint for free (26.6).
//
// Without an injected StyleContext (e.g. a component mounted in isolation) it is a
// transparent pass-through: it renders only the inner editor and forwards events, so no
// existing test or flow changes.
import { computed, inject, ref } from 'vue'
import type { SchemaField } from '../schema'
import { resolveFieldEditor } from '../fieldEditorRegistry'
import UnsupportedField from '../fields/UnsupportedField.vue'
import TokenPicker from '../designTokens/TokenPicker.vue'
import { StyleContextKey } from './styleContext'
import { tokenTypeFor, emptyValueFor } from './adoption'

const props = defineProps<{ field: SchemaField; modelValue: unknown }>()
const emit = defineEmits<{ update: [value: unknown]; commit: []; revert: [] }>()

const ctx = inject(StyleContextKey, null)

const descriptor = computed(() => resolveFieldEditor(props.field.type))
const inner = computed(() => descriptor.value?.component ?? UnsupportedField)
const needsField = computed(() => descriptor.value?.needsField ?? false)
const tokenType = computed(() => tokenTypeFor(props.field) ?? undefined)

// ---- token / source state (only meaningful with a context) -------------------
const registry = computed(() => ctx?.tokens() ?? null)
const hasTokens = computed(() => !!registry.value && (tokenType.value ? registry.value.byType(tokenType.value).length > 0 : registry.value.all().length > 0))
const boundKey = computed(() => registry.value?.referencedKey(props.modelValue) ?? null)
const isToken = computed(() => boundKey.value !== null)
const boundToken = computed(() => (boundKey.value ? registry.value?.get(boundKey.value) ?? null : null))
const preview = computed(() => {
  const v = registry.value?.resolve(props.modelValue)
  return typeof v === 'string' ? v : JSON.stringify(v)
})

function hasValue(v: unknown): boolean {
  if (v === undefined || v === null || v === '') return false
  if (typeof v === 'object') return Object.values(v as Record<string, unknown>).some((x) => x !== undefined && x !== null && x !== '')
  return true
}

// Source bucket for the badge: Token / Local / Global / Default.
const source = computed<'token' | 'local' | 'global' | 'default'>(() => {
  if (isToken.value) return 'token'
  if (hasValue(props.modelValue)) return 'local'
  if (!ctx) return 'local'
  const r = ctx.resolve({ componentType: ctx.componentType(), componentId: ctx.componentId(), property: props.field.name, localValue: props.modelValue })
  if (r.source === 'default' || r.source === 'property') return 'default'
  return 'global'
})
const sourceLabel = computed(() => ({ token: 'Token', local: 'Local', global: 'Global', default: 'Default' })[source.value])

// ---- editing ----------------------------------------------------------------
const showPicker = ref(false)

function onInner(value: unknown): void {
  emit('update', value)
}
function pickToken(reference: string): void {
  emit('update', reference) // TokenPicker already emits the wrapped `{{key}}`
  emit('commit')
  showPicker.value = false
}
function unbind(): void {
  emit('update', emptyValueFor(props.field))
  emit('commit')
  showPicker.value = false
}
</script>

<template>
  <!-- Transparent when no style context is available. -->
  <component
    v-if="!ctx"
    :is="inner"
    :model-value="modelValue"
    v-bind="needsField ? { field } : {}"
    @update="onInner"
    @commit="emit('commit')"
    @revert="emit('revert')"
  />

  <div v-else class="pb-style-bound">
    <div class="pb-style-bound__bar">
      <span class="pb-style-bound__source" :data-source="source">{{ sourceLabel }}</span>
      <button
        v-if="hasTokens && !isToken"
        type="button"
        class="pb-style-bound__token-btn"
        :class="{ 'is-open': showPicker }"
        :aria-pressed="showPicker"
        title="Bind to a design token"
        @click="showPicker = !showPicker"
      >
        {} Token
      </button>
    </div>

    <!-- Bound to a token: show a chip + resolved preview, not the raw editor. -->
    <div v-if="isToken" class="pb-style-bound__chip">
      <span v-if="tokenType === 'color'" class="pb-token-picker__swatch" :style="{ background: preview }" aria-hidden="true"></span>
      <span class="pb-style-bound__chip-name">{{ boundToken?.label ?? boundKey }}</span>
      <code class="pb-style-bound__chip-preview">{{ preview }}</code>
      <button type="button" class="pb-inspector-iconbtn" title="Use a custom value" aria-label="Unbind token" @click="unbind">✕</button>
    </div>

    <!-- Picking a token. -->
    <TokenPicker
      v-else-if="showPicker"
      :type="tokenType"
      :model-value="modelValue"
      @update="pickToken"
      @clear="unbind"
      @commit="emit('commit')"
    />

    <!-- Normal literal editing. -->
    <component
      v-else
      :is="inner"
      :model-value="modelValue"
      v-bind="needsField ? { field } : {}"
      @update="onInner"
      @commit="emit('commit')"
      @revert="emit('revert')"
    />
  </div>
</template>
