// Phase 26.6 — Responsive value model.
//
// A responsive property is stored as an envelope `{ desktop, tablet?, mobile? }`
// (subset allowed). This mirrors the server contract exactly: the PHP
// `ResponsiveResolver` treats an array as responsive iff every key is a breakpoint id
// and collapses it to `desktop` at render time; any other value passes through
// byte-identical. So a NON-responsive value (a plain string, or a composite object
// like `{ top, right, … }` whose keys are NOT breakpoint ids) is left untouched.
//
// These helpers are pure — no Vue, no side effects — so they are trivially testable
// and reused by the property field, the validator, and the live-preview path.

import { breakpointKeys, getBreakpoint, DEFAULT_BREAKPOINT } from './breakpoints'

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value)
}

/**
 * Whether `value` is a responsive envelope. Matches PHP `isResponsiveMap`: a
 * non-empty object whose keys are ALL breakpoint ids. A composite editor value
 * (spacing/typography/border/…) is therefore never mistaken for an envelope.
 */
export function isResponsiveEnvelope(value: unknown): value is Record<string, unknown> {
  if (!isPlainObject(value)) return false
  const keys = Object.keys(value)
  if (keys.length === 0) return false
  const known = breakpointKeys()
  return keys.every((k) => known.includes(k))
}

/**
 * The effective value shown while editing `device`. A plain value is itself for every
 * breakpoint. An envelope resolves via the breakpoint's authoring cascade, treating
 * only null/undefined (an ABSENT override) as "inherit" — an explicit empty string is
 * a real value (matching the server's `?? fallback`).
 */
export function resolveEffective(value: unknown, device: string): unknown {
  if (!isResponsiveEnvelope(value)) return value
  const chain = getBreakpoint(device)?.fallback ?? [device, DEFAULT_BREAKPOINT]
  for (const key of chain) {
    const v = value[key]
    if (v !== undefined && v !== null) return v
  }
  return value[DEFAULT_BREAKPOINT]
}

/** Whether `device` carries its OWN override (never true for the base breakpoint). */
export function hasOwnOverride(value: unknown, device: string): boolean {
  if (device === DEFAULT_BREAKPOINT) return false
  if (!isResponsiveEnvelope(value)) return false
  const v = value[device]
  return v !== undefined && v !== null
}

/**
 * Write `next` for `device`, returning a new value. Editing the base of a plain value
 * keeps it plain (no needless envelope). Editing any other breakpoint wraps into an
 * envelope, seeding `desktop` from the prior plain value so the base is preserved.
 */
export function setBreakpoint(value: unknown, device: string, next: unknown): unknown {
  if (device === DEFAULT_BREAKPOINT) {
    if (!isResponsiveEnvelope(value)) return next // stay plain
    return { ...value, [DEFAULT_BREAKPOINT]: next }
  }
  const base = isResponsiveEnvelope(value)
    ? { ...value }
    : value === undefined
      ? {}
      : { [DEFAULT_BREAKPOINT]: value }
  base[device] = next
  return base
}

/**
 * Remove `device`'s override. Collapses back to a plain value when only the base
 * remains, so clearing all overrides restores the original non-responsive shape.
 */
export function clearBreakpoint(value: unknown, device: string): unknown {
  if (device === DEFAULT_BREAKPOINT || !isResponsiveEnvelope(value)) return value
  const rest = { ...value }
  delete rest[device]
  const keys = Object.keys(rest)
  if (keys.length === 0) return undefined
  if (keys.length === 1 && keys[0] === DEFAULT_BREAKPOINT) return rest[DEFAULT_BREAKPOINT]
  return rest
}

/** The base (always-rendered) value, unwrapping an envelope. Used by validation. */
export function baseValue(value: unknown): unknown {
  return isResponsiveEnvelope(value) ? value[DEFAULT_BREAKPOINT] : value
}
