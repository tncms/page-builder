// Phase 26.38 — the global Ctrl/Cmd+I shortcut that OPENS the Insert launcher.
//
// It only opens the panel — it never inserts, resolves, dispatches a command, or touches
// BuilderState. It respects editor focus: while a text field / rich-text is focused the shortcut
// is ignored so normal typing (and the browser's own bindings) win. It reuses the existing Insert
// panel store; the panel itself owns focus + escape behaviour.

import { onMounted, onUnmounted } from 'vue'
import { useInsertPanelStore } from './insertPanelStore'

/** True when the event target is a text input / textarea / select / contenteditable. */
function isEditableTarget(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null
  if (!el) return false
  const tag = el.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable
}

/**
 * Decide + act on a keydown for the Insert shortcut. Returns whether it handled the event. Pure
 * enough to unit-test without a DOM: it calls `open` only for a bare Ctrl/Cmd+I outside a field.
 */
export function handleInsertShortcut(e: KeyboardEvent, open: () => void): boolean {
  if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey) return false
  if (e.key.toLowerCase() !== 'i') return false
  if (isEditableTarget(e.target)) return false
  e.preventDefault()
  open()
  return true
}

/** Attach the Ctrl/Cmd+I listener for the lifetime of the calling component. */
export function useInsertShortcut(): void {
  const store = useInsertPanelStore()
  const onKeydown = (e: KeyboardEvent): void => {
    handleInsertShortcut(e, () => store.open())
  }
  onMounted(() => document.addEventListener('keydown', onKeydown))
  onUnmounted(() => document.removeEventListener('keydown', onKeydown))
}
