// Phase 26.6 — Breakpoint registry.
//
// The canonical set of responsive breakpoints for property editing. The ids MUST
// match the server contract (`src/Support/ResponsiveValue.php` /
// `src/Runtime/ResponsiveResolver.php`): a value is only treated as responsive when
// every key is one of these ids, and the runtime collapses it to `desktop` at render
// time. The same ids are used by the frontend shell's device switcher
// (`resources/frontend-editor/shell/device.ts`), so wiring the two together later is
// a no-op mapping — that is the "device preview foundation" this phase prepares.

export interface Breakpoint {
  /** Stored envelope key + server device id. NEVER rename (breaks rendering). */
  id: string
  label: string
  /** A tiny glyph for the tab. */
  icon: string
  /** Sort order in the tab strip (desktop first). */
  order: number
  /**
   * Editor authoring cascade — most-specific first — used to resolve the *effective*
   * value shown while editing this breakpoint (an unset tablet inherits desktop).
   * The server currently renders `desktop` only, so this cascade is purely an
   * authoring convenience and never changes committed data.
   */
  fallback: string[]
}

/** The base breakpoint id — the always-present, always-rendered value. */
export const DEFAULT_BREAKPOINT = 'desktop'

const registry = new Map<string, Breakpoint>()

/** Register (or override) a breakpoint. */
export function registerBreakpoint(bp: Breakpoint): void {
  registry.set(bp.id, bp)
}

/** A breakpoint by id, or null. */
export function getBreakpoint(id: string): Breakpoint | null {
  return registry.get(id) ?? null
}

/** All breakpoints, ordered. */
export function listBreakpoints(): Breakpoint[] {
  return [...registry.values()].sort((a, b) => a.order - b.order)
}

/** Every registered breakpoint id (the responsive-envelope key set). */
export function breakpointKeys(): string[] {
  return [...registry.keys()]
}

// Built-in breakpoints (desktop = base). Registered on import.
registerBreakpoint({ id: 'desktop', label: 'Desktop', icon: '🖥', order: 0, fallback: ['desktop'] })
registerBreakpoint({ id: 'tablet', label: 'Tablet', icon: '▭', order: 1, fallback: ['tablet', 'desktop'] })
registerBreakpoint({ id: 'mobile', label: 'Mobile', icon: '▯', order: 2, fallback: ['mobile', 'tablet', 'desktop'] })
