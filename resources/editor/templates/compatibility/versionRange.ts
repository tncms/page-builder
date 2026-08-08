// Phase 26.27 — a minimal, dependency-free semver range check.
//
// Supports what template compatibility needs: exact versions (`1.2.3`, `1.0`), space-separated
// comparator ranges (`>=1.0`, `>=1.0 <2.0`), and `*` (any). Not a full semver implementation
// (no pre-release, no `^`/`~`, no `||`) — deliberately small for the foundation.

interface Version {
  major: number
  minor: number
  patch: number
}

const EXACT = /^\d+\.\d+\.\d+$/
const COMPARATOR = /^(>=|<=|>|<|=)?\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?$/

/** Parse `X`, `X.Y`, or `X.Y.Z` into a version (missing parts = 0), or null when malformed. */
export function parseVersion(input: string): Version | null {
  const m = /^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/.exec(input.trim())
  if (!m) return null
  return { major: Number(m[1]), minor: Number(m[2] ?? 0), patch: Number(m[3] ?? 0) }
}

/** True when `value` is an exact `X.Y.Z` version. */
export function isExactVersion(value: string): boolean {
  return EXACT.test(value.trim())
}

function compare(a: Version, b: Version): number {
  return a.major - b.major || a.minor - b.minor || a.patch - b.patch
}

/** True when every comparator in `range` parses. */
export function isValidRange(range: string): boolean {
  const trimmed = range.trim()
  if (trimmed === '' || trimmed === '*') return true
  return trimmed.split(/\s+/).every((c) => COMPARATOR.test(c))
}

/** True when `version` satisfies `range` (AND of space-separated comparators). */
export function satisfiesRange(version: string, range: string): boolean {
  const target = parseVersion(version)
  if (!target) return false
  const trimmed = range.trim()
  if (trimmed === '' || trimmed === '*') return true

  return trimmed.split(/\s+/).every((comparator) => {
    const m = COMPARATOR.exec(comparator)
    if (!m) return false
    const op = m[1] ?? '='
    const rhs: Version = { major: Number(m[2]), minor: Number(m[3] ?? 0), patch: Number(m[4] ?? 0) }
    const cmp = compare(target, rhs)
    switch (op) {
      case '>=':
        return cmp >= 0
      case '>':
        return cmp > 0
      case '<=':
        return cmp <= 0
      case '<':
        return cmp < 0
      default:
        return cmp === 0
    }
  })
}
