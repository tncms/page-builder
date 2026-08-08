<script setup lang="ts">
// Phase 26.37 — the unified Insert launcher. Phase 26.38 — Builder UX (keyboard nav, escape,
// focus restoration, accessible category navigation).
//
// One entry that lists the registered insert categories; picking one ROUTES to its existing
// experience (Pattern Picker, Template Browser, Asset Picker, component palette) via the store. It
// inserts nothing itself — no resolve, no command, no BuilderState.
import { nextTick, ref, watch } from 'vue'
import { useInsertPanelStore } from './insertPanelStore'

const store = useInsertPanelStore()

// The category buttons, for roving keyboard focus.
const catButtons = ref<HTMLButtonElement[]>([])
// The element that had focus before the panel opened, so we can restore it on close.
let restoreFocusTo: HTMLElement | null = null

function focusIndex(index: number): void {
  const buttons = catButtons.value
  if (!buttons.length) return
  const clamped = (index + buttons.length) % buttons.length
  buttons[clamped]?.focus()
}

/** Roving focus across categories; Home/End jump to the ends. Escape is handled on the dialog. */
function onGridKeydown(e: KeyboardEvent): void {
  const buttons = catButtons.value
  if (!buttons.length) return
  const current = buttons.indexOf(document.activeElement as HTMLButtonElement)
  switch (e.key) {
    case 'ArrowDown':
    case 'ArrowRight':
      e.preventDefault()
      focusIndex(current + 1)
      break
    case 'ArrowUp':
    case 'ArrowLeft':
      e.preventDefault()
      focusIndex(current - 1)
      break
    case 'Home':
      e.preventDefault()
      focusIndex(0)
      break
    case 'End':
      e.preventDefault()
      focusIndex(buttons.length - 1)
      break
  }
}

// On open: remember focus, then move it to the active (or first) category. On close: restore it.
watch(
  () => store.isOpen,
  async (open) => {
    if (open) {
      restoreFocusTo = document.activeElement as HTMLElement | null
      await nextTick()
      const active = store.activeKey
        ? store.providers.findIndex((p) => p.key === store.activeKey)
        : 0
      focusIndex(active < 0 ? 0 : active)
    } else if (restoreFocusTo) {
      restoreFocusTo.focus?.()
      restoreFocusTo = null
    }
  },
)
</script>

<template>
  <div
    v-if="store.isOpen"
    class="pb-insert"
    role="dialog"
    aria-modal="true"
    aria-label="Insert"
    @keydown.esc.stop="store.close()"
    @click.self="store.close()"
  >
    <div class="pb-insert__panel">
      <header class="pb-insert__head">
        <h2 class="pb-insert__title">Insert</h2>
        <button type="button" class="pb-insert__close" @click="store.close()" aria-label="Close">×</button>
      </header>

      <p v-if="store.isEmpty" class="pb-insert__empty">No insert categories are available.</p>

      <ul v-else class="pb-insert__grid" role="menu" @keydown="onGridKeydown">
        <li v-for="provider in store.providers" :key="provider.key" role="none">
          <button
            ref="catButtons"
            type="button"
            role="menuitem"
            class="pb-insert__cat"
            :class="{ 'is-active': store.activeKey === provider.key }"
            @click="store.select(provider.key)"
          >
            <span class="pb-insert__cat-label">{{ provider.label }}</span>
            <span v-if="provider.description" class="pb-insert__cat-desc">{{ provider.description }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.pb-insert {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.5);
  z-index: 50;
}
.pb-insert__panel {
  width: min(520px, 100%);
  background: #fff;
  border-radius: 0.6rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  padding: 1rem 1.25rem 1.25rem;
}
.pb-insert__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.pb-insert__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.pb-insert__close {
  border: 0;
  background: transparent;
  font-size: 1.4rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
}
.pb-insert__empty {
  margin: 1rem 0 0;
  color: #64748b;
  font-size: 0.8rem;
}
.pb-insert__grid {
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.6rem;
}
.pb-insert__cat {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.8rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.pb-insert__cat:hover {
  border-color: #94a3b8;
}
.pb-insert__cat:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}
.pb-insert__cat.is-active {
  border-color: #2563eb;
}
.pb-insert__cat-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #0f172a;
}
.pb-insert__cat-desc {
  font-size: 0.68rem;
  color: #64748b;
}
</style>
