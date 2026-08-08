<script setup lang="ts">
// Phase 18 — Inline overlay input. A real <input>/<textarea> (NOT contenteditable)
// positioned exactly over the iframe text being edited, copying its typography so
// it looks like editing the real text. Typing streams to the store (live patch);
// commit happens on blur / Enter / Ctrl+Enter, cancel on Escape.
//
// HOTFIX: it now renders INSIDE the `.pb-fe-overlay-viewport` host (positioned
// over the iframe), so its position IS the iframe-reported rect verbatim —
// host-local coordinates, no screen offset to add. The host stays glued to the
// iframe, so this follows the text through scroll/resize without its own listeners.
import { computed, nextTick, ref, watch } from 'vue'
import { useInlineStore } from './InlineManager'
import { autoResize, placeCaretAtEnd, type TextControl } from './CaretManager'

const inline = useInlineStore()
const control = ref<TextControl | null>(null)
const position = ref<Record<string, string>>({ display: 'none' })

const boxStyle = computed<Record<string, string>>(() => ({
  ...position.value,
  ...inline.typography,
  position: 'absolute',
  margin: '0',
  border: '0',
  outline: '0',
  background: 'transparent',
  resize: 'none',
  overflow: 'hidden',
  boxSizing: 'border-box',
  zIndex: '2147483001',
  whiteSpace: inline.multiline ? 'pre-wrap' : 'nowrap',
}))

function reposition(): void {
  const rect = inline.rect
  if (!inline.active || !rect) {
    position.value = { display: 'none' }
    return
  }

  // Host-local: the overlay host sits over the iframe, so the iframe-viewport
  // rect is already the correct position — no frame offset to add.
  position.value = {
    display: 'block',
    top: `${rect.top}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    minHeight: `${rect.height}px`,
  }
}

function onInput(event: Event): void {
  const el = event.target as TextControl
  inline.setValue(el.value)
  if (inline.multiline && el instanceof HTMLTextAreaElement) autoResize(el)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    inline.cancel()
    return
  }

  if (event.key === 'Enter') {
    // Single-line: Enter commits. Multiline: only Ctrl/Cmd+Enter commits; a plain
    // Enter inserts a newline (default behaviour).
    if (!inline.multiline || event.ctrlKey || event.metaKey) {
      event.preventDefault()
      void inline.commit()
    }
  }
}

function onBlur(): void {
  // commit() is a no-op if the session was already ended (e.g. via Escape).
  void inline.commit()
}

// Focus + caret + measure whenever a session opens.
watch(
  () => inline.active,
  async (active) => {
    if (!active) {
      reposition()
      return
    }
    await nextTick()
    reposition()
    const el = control.value
    if (el) {
      el.focus()
      placeCaretAtEnd(el)
      if (inline.multiline && el instanceof HTMLTextAreaElement) autoResize(el)
    }
  },
)

// Follow the element as it reflows / the iframe scrolls (the iframe re-sends its
// rect on scroll/resize, bumping inline.rect / inline.version). The overlay host
// tracks the iframe's own screen movement, so no window listeners are needed here.
watch(() => [inline.rect, inline.version], () => reposition())
</script>

<template>
  <textarea
    v-if="inline.active && inline.multiline"
    ref="control"
    class="pb-fe-inline-input"
    :value="inline.value"
    :style="boxStyle"
    rows="1"
    @input="onInput"
    @keydown="onKeydown"
    @blur="onBlur"
  ></textarea>
  <input
    v-else-if="inline.active"
    ref="control"
    class="pb-fe-inline-input"
    type="text"
    :value="inline.value"
    :style="boxStyle"
    @input="onInput"
    @keydown="onKeydown"
    @blur="onBlur"
  />
</template>
