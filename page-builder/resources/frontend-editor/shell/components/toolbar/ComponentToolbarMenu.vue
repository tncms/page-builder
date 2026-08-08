<script setup lang="ts">
// Phase 22 — Reusable dropdown shell for the toolbar's "More" menu. Owns the
// keyboard + dismissal behaviour so the menu content stays declarative:
//   - focuses the first enabled item on open
//   - ArrowUp/Down + Home/End roving focus over [role=menuitem]
//   - Escape or an outside click closes it (emits `close`)
// Activation (Enter/Space) is the native <button> behaviour of each item.
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const root = ref<HTMLElement | null>(null)

function items(): HTMLElement[] {
  if (!root.value) return []
  return Array.from(
    root.value.querySelectorAll<HTMLElement>('[role="menuitem"]:not([disabled])'),
  )
}

function focusAt(index: number): void {
  const list = items()
  if (!list.length) return
  const clamped = ((index % list.length) + list.length) % list.length
  list[clamped]?.focus()
}

function currentIndex(): number {
  return items().indexOf(document.activeElement as HTMLElement)
}

function onKeydown(event: KeyboardEvent): void {
  switch (event.key) {
    case 'Escape':
      event.preventDefault()
      emit('close')
      break
    case 'ArrowDown':
      event.preventDefault()
      focusAt(currentIndex() + 1)
      break
    case 'ArrowUp':
      event.preventDefault()
      focusAt(currentIndex() - 1)
      break
    case 'Home':
      event.preventDefault()
      focusAt(0)
      break
    case 'End':
      event.preventDefault()
      focusAt(items().length - 1)
      break
    default:
      break
  }
}

function onDocumentPointer(event: Event): void {
  if (root.value && !root.value.contains(event.target as Node)) emit('close')
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      await nextTick()
      focusAt(0)
      document.addEventListener('pointerdown', onDocumentPointer, true)
    } else {
      document.removeEventListener('pointerdown', onDocumentPointer, true)
    }
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointer, true)
})
</script>

<template>
  <div
    v-if="open"
    ref="root"
    class="pb-live-toolbar__menu"
    role="menu"
    @keydown="onKeydown"
  >
    <slot />
  </div>
</template>
