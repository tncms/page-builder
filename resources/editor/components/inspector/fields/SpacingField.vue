<script setup lang="ts">
// Phase 26.5 — Spacing editor. Edits a { top, right, bottom, left } object with an
// optional linked mode: when linked, editing one side updates all four. Linked is
// local UI state (never persisted); the value shape is unchanged. Units and
// per-breakpoint values layer onto this same object path as a future capability.
import { computed, ref } from 'vue'

type Spacing = { top?: unknown; right?: unknown; bottom?: unknown; left?: unknown }
const SIDES = ['top', 'right', 'bottom', 'left'] as const

const props = defineProps<{ modelValue: unknown }>()
const emit = defineEmits<{ update: [value: Spacing]; commit: []; revert: [] }>()

const current = computed<Spacing>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Spacing)
    : {},
)

function sidesEqual(s: Spacing): boolean {
  const vals = SIDES.map((k) => (s[k] as string) ?? '')
  return vals.every((v) => v === vals[0])
}

// Start linked when the current sides are uniform (the common case for new fields).
const linked = ref(sidesEqual(current.value))

function onInput(side: (typeof SIDES)[number], e: Event): void {
  const v = (e.target as HTMLInputElement).value
  if (linked.value) {
    emit('update', { top: v, right: v, bottom: v, left: v })
  } else {
    emit('update', { ...current.value, [side]: v })
  }
}

function toggleLinked(): void {
  linked.value = !linked.value
  // Re-linking with mixed values normalizes to the top value so the sides agree.
  if (linked.value && !sidesEqual(current.value)) {
    const v = (current.value.top as string) ?? ''
    emit('update', { top: v, right: v, bottom: v, left: v })
    emit('commit')
  }
}
</script>

<template>
  <div class="pb-inspector-spacing-editor">
    <button
      type="button"
      class="pb-inspector-iconbtn pb-inspector-spacing__link"
      :class="{ 'is-linked': linked }"
      :aria-pressed="linked"
      :title="linked ? 'Sides linked — click to edit each side' : 'Sides unlinked — click to link'"
      @click="toggleLinked"
    >
      {{ linked ? '🔗' : '🔓' }}
    </button>
    <div class="pb-inspector-spacing">
      <label v-for="side in SIDES" :key="side" class="pb-inspector-spacing__side">
        <span>{{ side }}</span>
        <input
          type="text"
          class="pb-inspector-input"
          :value="(current[side] as string) ?? ''"
          @input="onInput(side, $event)"
          @blur="emit('commit')"
          @keydown.enter.prevent="emit('commit')"
          @keydown.esc.prevent="emit('revert')"
        />
      </label>
    </div>
  </div>
</template>
