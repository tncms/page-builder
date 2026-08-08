<script setup lang="ts">
// Phase 26.7 — Token picker (reusable foundation).
//
// Lists the tokens from `metadata.tokens` (optionally filtered to one group) and lets
// the user bind a field to one. Picking a token emits the canonical wrapped reference
// string `{{group.name}}` — never an object — so the committed value resolves through
// the existing PHP `DesignTokenResolver` unchanged.
//
// This is infrastructure: it is not wired into any editor yet. An editor adopts it by
// rendering <TokenPicker :type="'color'" :model-value="value" @update .../> behind a
// "use token" affordance. Kept prop-driven + emit-only so it is testable in isolation.
import { computed } from 'vue'
import { useTokens } from './useTokens'
import { makeTokenReference } from './tokenReference'
import { tokenGroupLabel } from './tokenTypes'
import type { DesignToken } from './tokenTypes'

const props = defineProps<{
  /** The field's current value (a token reference or a literal). */
  modelValue: unknown
  /** Restrict to one token group, e.g. `color`. Omit to show all groups. */
  type?: string
}>()
const emit = defineEmits<{ update: [reference: string]; clear: []; commit: [] }>()

const registry = useTokens()

// Tokens to offer, grouped for display.
const grouped = computed<Array<{ group: string; label: string; tokens: DesignToken[] }>>(() => {
  const groups = props.type ? [props.type] : registry.value.groups()
  return groups
    .map((group) => ({ group, label: tokenGroupLabel(group), tokens: registry.value.byType(group) }))
    .filter((g) => g.tokens.length > 0)
})

// The key currently bound (only when it is a REAL, known token — not a coincidental literal).
const activeKey = computed(() => registry.value.referencedKey(props.modelValue))

// A CSS color for a color-token swatch, else null.
function swatch(token: DesignToken): string | null {
  return token.group === 'color' && typeof token.value === 'string' ? token.value : null
}
function preview(token: DesignToken): string {
  return typeof token.value === 'string' ? token.value : JSON.stringify(token.value)
}

function pick(token: DesignToken): void {
  emit('update', makeTokenReference(token.key))
  emit('commit')
}
function clear(): void {
  emit('clear')
  emit('commit')
}
</script>

<template>
  <div class="pb-token-picker">
    <div v-if="!grouped.length" class="pb-inspector-hint">No design tokens available.</div>

    <div v-for="group in grouped" :key="group.group" class="pb-token-picker__group">
      <div class="pb-token-picker__group-label">{{ group.label }}</div>
      <div class="pb-token-picker__list">
        <button
          v-for="token in group.tokens"
          :key="token.key"
          type="button"
          class="pb-token-picker__item"
          :class="{ 'is-active': token.key === activeKey }"
          :title="`${token.key} — ${preview(token)}`"
          :data-token="token.key"
          @click="pick(token)"
        >
          <span
            v-if="swatch(token)"
            class="pb-token-picker__swatch"
            :style="{ background: swatch(token) as string }"
            aria-hidden="true"
          ></span>
          <span class="pb-token-picker__name">{{ token.label }}</span>
        </button>
      </div>
    </div>

    <button
      v-if="activeKey"
      type="button"
      class="pb-token-picker__clear"
      @click="clear"
    >
      Use a custom value
    </button>
  </div>
</template>
