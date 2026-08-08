// Phase 17 — Text patcher. Replaces an element's visible text with the current
// (pre-commit) inspector value, preserving line breaks the way the server render
// does (nl2br). Returns a restore fn that puts back the original markup.

import type { Restore } from './stylePatcher'

/** Set `el` text to `value` (newlines -> <br>), returning a restore fn. */
export function applyText(el: HTMLElement, value: string): Restore {
  const priorHtml = el.innerHTML
  el.textContent = ''

  const lines = value.split('\n')
  lines.forEach((line, index) => {
    el.appendChild(document.createTextNode(line))
    if (index < lines.length - 1) {
      el.appendChild(document.createElement('br'))
    }
  })

  return () => {
    el.innerHTML = priorHtml
  }
}
