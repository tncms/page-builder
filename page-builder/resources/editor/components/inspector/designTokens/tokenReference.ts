// Phase 26.7 — Token reference syntax.
//
// A token is referenced from a component value by a STRING — never an object. This
// mirrors the PHP `TokenResolver` exactly (`src/Design/TokenResolver.php`), which only
// resolves string values and passes everything else through unchanged. Emitting an
// object like `{ token: "color.primary" }` would NOT be resolved server-side and would
// break rendering, so the editor always produces a string.
//
// Two forms are accepted (both resolve server-side):
//   - wrapped  `{{group.name}}`  ← canonical: unambiguous, never mistaken for a literal
//   - bare     `group.name`      ← also valid; kept for compatibility on read
//
// The editor EMITS the wrapped form. On read it recognizes both. A bare string like
// `0.5rem` matches the bare pattern too, so "is this a real token?" must additionally
// check the registry — see `TokenRegistry.resolve` / `TokenRegistry.referencedKey`.

// A single `group.name` segment pair (mirrors the PHP character class + single dot).
const KEY = '[a-z0-9_-]+\\.[a-z0-9_-]+'
const BARE = new RegExp(`^${KEY}$`, 'i')
const WRAPPED = new RegExp(`^\\{\\{\\s*(${KEY})\\s*\\}\\}$`, 'i')

/** The dotted key a value references (either form), or null when it is not a reference. */
export function parseTokenReference(value: unknown): string | null {
  if (typeof value !== 'string') return null
  const v = value.trim()
  const wrapped = v.match(WRAPPED)
  if (wrapped) return wrapped[1]
  if (BARE.test(v)) return v
  return null
}

/** Whether `value` is shaped like a token reference (does not check the registry). */
export function isTokenReference(value: unknown): boolean {
  return parseTokenReference(value) !== null
}

/** Build the canonical (wrapped) reference for a token key. */
export function makeTokenReference(key: string): string {
  return `{{${key}}}`
}
