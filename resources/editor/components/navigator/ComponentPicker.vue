<script setup lang="ts">
// Reusable component picker (Phase 26.3, Increment 6).
//
// The chooser the Navigator opens for Add Inside / Add Before / Add After. It only
// RENDERS the candidate definitions handed to it — candidate resolution and the
// NestingPolicy filtering happen upstream (utils/navigatorInsert), so this
// component owns no business logic and no duplicated component list: just
// presentation, keyboard roving, and pick/close events. That keeps it reusable by
// any future surface (e.g. Increment 7 drag) that already has a candidate list.
import { nextTick, onMounted, ref } from 'vue'
import type { DefinitionMeta } from '../../types'

const props = defineProps<{ candidates: DefinitionMeta[]; title?: string }>()
const emit = defineEmits<{ (e: 'pick', def: DefinitionMeta): void; (e: 'close'): void }>()

const rootEl = ref<HTMLElement | null>(null)

function labelOf(def: DefinitionMeta): string {
  return (def.label as string) || (def.name as string) || def.type
}

// Live-queried each keystroke so roving always reflects the rendered order.
function items(): HTMLButtonElement[] {
  const root = rootEl.value
  return root ? Array.from(root.querySelectorAll<HTMLButtonElement>('button[role="menuitem"]')) : []
}

function focusItem(index: number): void {
  const list = items()
  if (list.length === 0) return
  const n = list.length
  // Arrows wrap; Home/End pass 0 / n-1 which clamp naturally.
  list[((index % n) + n) % n].focus()
}

function activeIndex(): number {
  const active = typeof document !== 'undefined' ? document.activeElement : null
  return items().indexOf(active as HTMLButtonElement)
}

function onKeydown(e: KeyboardEvent): void {
  switch (e.key) {
    case 'ArrowDown': {
      e.preventDefault()
      const i = activeIndex()
      focusItem(i < 0 ? 0 : i + 1)
      break
    }
    case 'ArrowUp': {
      e.preventDefault()
      const i = activeIndex()
      focusItem(i < 0 ? items().length - 1 : i - 1)
      break
    }
    case 'Home':
      e.preventDefault()
      focusItem(0)
      break
    case 'End':
      e.preventDefault()
      focusItem(items().length - 1)
      break
    case 'Escape':
      e.preventDefault()
      e.stopPropagation()
      emit('close')
      break
    case 'Tab':
      // Never let Tab strand focus behind an open picker.
      e.preventDefault()
      emit('close')
      break
  }
}

// Move focus onto the first candidate so keyboard users land inside the picker.
onMounted(() => {
  void nextTick(() => focusItem(0))
})
</script>

<template>
  <div
    ref="rootEl"
    class="pb-editor-navigator-picker"
    role="menu"
    :aria-label="title || 'Add component'"
    @keydown="onKeydown"
    @mouseleave="emit('close')"
  >
    <p v-if="title" class="pb-editor-navigator-picker__title">{{ title }}</p>
    <p v-if="candidates.length === 0" class="pb-editor-navigator-picker__empty">
      No components can be placed here.
    </p>
    <button
      v-for="def in candidates"
      :key="def.type"
      type="button"
      role="menuitem"
      class="pb-editor-navigator-picker__item"
      @click.stop="emit('pick', def)"
    >
      <span class="pb-editor-navigator-picker__label">{{ labelOf(def) }}</span>
      <code class="pb-editor-navigator-picker__type">{{ def.type }}</code>
    </button>
  </div>
</template>
