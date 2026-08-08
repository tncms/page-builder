// Phase 17 — Attribute patcher. Sets a single attribute (e.g. href) on a target
// element, returning a restore fn that reinstates the prior value (or removes it).

import type { Restore } from './stylePatcher'

/** Set `attr` on `el` to `value`, returning a restore fn. */
export function applyAttr(el: HTMLElement, attr: string, value: string): Restore {
  const had = el.hasAttribute(attr)
  const prior = el.getAttribute(attr)

  el.setAttribute(attr, value)

  return () => {
    if (had && prior !== null) {
      el.setAttribute(attr, prior)
    } else {
      el.removeAttribute(attr)
    }
  }
}
