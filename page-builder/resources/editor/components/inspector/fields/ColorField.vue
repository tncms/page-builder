<script setup lang="ts">
// Phase 26.5 — Color editor: swatch + text value (so tokens / named colors can be
// typed too), opacity, reset, and a session-shared recent-colors palette.
//
// The value stays a plain string for backward-compatibility: `#rrggbb`, or
// `#rrggbbaa` when opacity < 100 (both valid CSS colors), or an arbitrary token /
// named color. Opacity only applies to hex values; for tokens the alpha control is
// hidden because we can't losslessly attach an alpha channel to a token here. Global
// colors / theme tokens will surface as extra swatches on this same string value.
import { computed } from 'vue'
import { recentColors, pushRecentColor } from '../recentColors'

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: string]; commit: []; revert: [] }>()

const value = computed(() => (props.modelValue as string) ?? '')

/** Parse `#rrggbb` / `#rrggbbaa` into a 6-digit hex + 0–100 opacity; null for tokens. */
const parsed = computed<{ hex6: string; alpha: number } | null>(() => {
  const v = value.value.trim()
  if (/^#[0-9a-f]{6}$/i.test(v)) return { hex6: v, alpha: 100 }
  if (/^#[0-9a-f]{8}$/i.test(v)) {
    const a = parseInt(v.slice(7, 9), 16)
    return { hex6: v.slice(0, 7), alpha: Math.round((a / 255) * 100) }
  }
  return null
})

const isHex = computed(() => parsed.value !== null)
const swatch = computed(() => parsed.value?.hex6 ?? '#000000')
const opacity = computed(() => parsed.value?.alpha ?? 100)

/** Compose a hex6 + 0–100 opacity back into `#rrggbb` (100%) or `#rrggbbaa`. */
function compose(hex6: string, alpha: number): string {
  const a = Math.max(0, Math.min(100, Math.round(alpha)))
  if (a >= 100) return hex6
  const hex = Math.round((a / 100) * 255).toString(16).padStart(2, '0')
  return `${hex6}${hex}`
}

function onSwatch(hex6: string): void {
  emit('update', compose(hex6, opacity.value))
}
function onOpacity(next: number): void {
  const base = parsed.value?.hex6 ?? '#000000'
  emit('update', compose(base, next))
  emit('commit')
}
function onText(v: string): void {
  emit('update', v)
}
function commit(): void {
  pushRecentColor(value.value)
  emit('commit')
}
function reset(): void {
  emit('update', '')
  emit('commit')
}
function pick(color: string): void {
  emit('update', color)
  pushRecentColor(color)
  emit('commit')
}
</script>

<template>
  <div class="pb-inspector-color-editor">
    <div class="pb-inspector-color">
      <input
        type="color"
        class="pb-inspector-color__swatch"
        :value="swatch"
        @input="onSwatch(($event.target as HTMLInputElement).value)"
        @change="commit"
      />
      <input
        type="text"
        class="pb-inspector-input"
        :value="value"
        placeholder="#000000 or token"
        @input="onText(($event.target as HTMLInputElement).value)"
        @blur="commit"
        @keydown.enter.prevent="commit"
        @keydown.esc.prevent="emit('revert')"
      />
      <button
        v-if="value"
        type="button"
        class="pb-inspector-iconbtn"
        title="Reset color"
        aria-label="Reset color"
        @click="reset"
      >
        ✕
      </button>
    </div>

    <label v-if="isHex" class="pb-inspector-color__opacity">
      <span>Opacity</span>
      <input
        type="range"
        min="0"
        max="100"
        step="1"
        :value="opacity"
        @input="onOpacity(Number(($event.target as HTMLInputElement).value))"
      />
      <output>{{ opacity }}%</output>
    </label>

    <div v-if="recentColors.length" class="pb-inspector-color__recent" aria-label="Recent colors">
      <button
        v-for="color in recentColors"
        :key="color"
        type="button"
        class="pb-inspector-color__recent-swatch"
        :style="{ background: color }"
        :title="color"
        :aria-label="`Use ${color}`"
        @click="pick(color)"
      ></button>
    </div>
  </div>
</template>
