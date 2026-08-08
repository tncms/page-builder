<script setup lang="ts">
// Phase 26.5 — Border editor. Edits a { width, style, color, radius } object.
// `radius` is a single value today; per-corner control is a future capability that
// layers onto this same value path (radius → { topLeft, … }) without a shape break.
import { computed } from 'vue'

type Border = { width?: unknown; style?: unknown; color?: unknown; radius?: unknown }

const STYLES = ['none', 'solid', 'dashed', 'dotted', 'double'] as const

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: Border]; commit: []; revert: [] }>()

const current = computed<Border>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Border)
    : {},
)

function patch(key: keyof Border, value: unknown): void {
  emit('update', { ...current.value, [key]: value })
}

const colorText = computed(() => (current.value.color as string) ?? '')
// The native swatch needs a #rrggbb; fall back to black for tokens / empty.
const swatch = computed(() => (/^#[0-9a-f]{6}$/i.test(colorText.value) ? colorText.value : '#000000'))
</script>

<template>
  <div class="pb-inspector-border">
    <label class="pb-inspector-border__row">
      <span>Width</span>
      <input
        type="text"
        class="pb-inspector-input"
        placeholder="1px"
        :value="(current.width as string) ?? ''"
        @input="patch('width', ($event.target as HTMLInputElement).value)"
        @blur="emit('commit')"
        @keydown.enter.prevent="emit('commit')"
        @keydown.esc.prevent="emit('revert')"
      />
    </label>

    <label class="pb-inspector-border__row">
      <span>Style</span>
      <select
        class="pb-inspector-input"
        :value="(current.style as string) ?? ''"
        @change="patch('style', ($event.target as HTMLSelectElement).value); emit('commit')"
      >
        <option value="">—</option>
        <option v-for="s in STYLES" :key="s" :value="s">{{ s }}</option>
      </select>
    </label>

    <label class="pb-inspector-border__row">
      <span>Color</span>
      <span class="pb-inspector-color">
        <input
          type="color"
          class="pb-inspector-color__swatch"
          :value="swatch"
          @input="patch('color', ($event.target as HTMLInputElement).value)"
          @change="emit('commit')"
        />
        <input
          type="text"
          class="pb-inspector-input"
          placeholder="#000000 or token"
          :value="colorText"
          @input="patch('color', ($event.target as HTMLInputElement).value)"
          @blur="emit('commit')"
          @keydown.enter.prevent="emit('commit')"
          @keydown.esc.prevent="emit('revert')"
        />
      </span>
    </label>

    <label class="pb-inspector-border__row">
      <span>Radius</span>
      <input
        type="text"
        class="pb-inspector-input"
        placeholder="0"
        :value="(current.radius as string) ?? ''"
        @input="patch('radius', ($event.target as HTMLInputElement).value)"
        @blur="emit('commit')"
        @keydown.enter.prevent="emit('commit')"
        @keydown.esc.prevent="emit('revert')"
      />
    </label>
  </div>
</template>
