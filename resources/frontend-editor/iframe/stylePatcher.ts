// Phase 17 — Inline style patcher. Applies only whitelisted CSS properties to a
// target element and returns a restore fn capturing the prior inline values, so a
// clear-patches can revert to exactly what was there before.

import { isWhitelistedStyle } from './patchMap'

export type Restore = () => void

/**
 * Apply a map of camelCase CSS props to `el`. Non-whitelisted props are ignored.
 * An empty/null value removes the inline prop. Returns a restore fn.
 */
export function applyStyle(el: HTMLElement, style: Record<string, unknown>): Restore {
  const previous: Array<[string, string]> = []

  for (const [prop, raw] of Object.entries(style)) {
    if (!isWhitelistedStyle(prop)) continue

    // Capture the prior inline value once per prop for restoration.
    previous.push([prop, el.style.getPropertyValue(toKebab(prop))])

    const value = raw == null ? '' : String(raw)
    if (value === '') {
      el.style.removeProperty(toKebab(prop))
    } else {
      el.style.setProperty(toKebab(prop), value)
    }
  }

  return () => {
    for (const [prop, prior] of previous) {
      if (prior === '') {
        el.style.removeProperty(toKebab(prop))
      } else {
        el.style.setProperty(toKebab(prop), prior)
      }
    }
  }
}

function toKebab(prop: string): string {
  return prop.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)
}
