<script setup lang="ts">
// Phase 26.5 — Background editor (foundation: background color).
//
// The value is a discriminated object `{ type: 'color', color }`. The `type` tag is
// the seam for future background kinds (gradient / image / video / pattern): adding
// them layers new branches onto the same value shape without breaking existing
// `color` backgrounds. Only `color` is implemented today.
import { computed } from 'vue'

type Background = { type?: unknown; color?: unknown }

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: Background | null]; commit: []; revert: [] }>()

const current = computed<Background>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Background)
    : {},
)

const colorText = computed(() => (current.value.color as string) ?? '')
const swatch = computed(() => (/^#[0-9a-f]{6}$/i.test(colorText.value) ? colorText.value : '#000000'))

function setColor(color: string): void {
  emit('update', { type: 'color', color })
}

function reset(): void {
  emit('update', null)
  emit('commit')
}
</script>

<template>
  <div class="pb-inspector-background">
    <div class="pb-inspector-background__row">
      <span class="pb-inspector-background__label">Color</span>
      <span class="pb-inspector-color">
        <input
          type="color"
          class="pb-inspector-color__swatch"
          :value="swatch"
          @input="setColor(($event.target as HTMLInputElement).value)"
          @change="emit('commit')"
        />
        <input
          type="text"
          class="pb-inspector-input"
          placeholder="#000000 or token"
          :value="colorText"
          @input="setColor(($event.target as HTMLInputElement).value)"
          @blur="emit('commit')"
          @keydown.enter.prevent="emit('commit')"
          @keydown.esc.prevent="emit('revert')"
        />
        <button
          v-if="colorText"
          type="button"
          class="pb-inspector-iconbtn"
          title="Clear background"
          aria-label="Clear background"
          @click="reset"
        >
          ✕
        </button>
      </span>
    </div>
    <p class="pb-inspector-hint">Gradient, image, and video backgrounds are coming in a later phase.</p>
  </div>
</template>
