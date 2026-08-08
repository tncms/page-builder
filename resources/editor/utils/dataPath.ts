// Phase 15 — immutable dot-path helpers for component data editing.
//
// The Property Inspector edits component.data by dotted field names
// ("style.background", "spacing.padding") and must never mutate the reactive
// payload in place. Every write returns a fresh, plain, JSON-safe object.
// structuredClone throws on Vue reactive proxies, so cloning goes through
// toRaw + JSON (the pattern established in Phase 14.2).

import { toRaw } from 'vue'

/** Deep clone into a plain JSON-safe value. Never uses structuredClone. */
export function clonePlain<T>(value: T): T {
  try {
    return JSON.parse(JSON.stringify(toRaw(value ?? null) ?? null)) as T
  } catch {
    return (Array.isArray(value) ? [] : {}) as T
  }
}

function keysOf(path: string): string[] {
  return path.split('.').filter((k) => k !== '')
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

/** Read a dotted path, returning `fallback` when any segment is missing. */
export function getByPath(obj: unknown, path: string, fallback: unknown = undefined): unknown {
  const keys = keysOf(path)
  if (keys.length === 0) return fallback
  let cur: unknown = obj
  for (const key of keys) {
    if (!isPlainObject(cur)) return fallback
    cur = cur[key]
  }
  return cur === undefined ? fallback : cur
}

/** Return a new object with `path` set to `value` (deep, immutable, plain). */
export function setByPath(obj: unknown, path: string, value: unknown): Record<string, unknown> {
  const root = clonePlain(isPlainObject(obj) ? obj : {})
  const keys = keysOf(path)
  if (keys.length === 0) return root

  let cur = root
  for (let i = 0; i < keys.length - 1; i++) {
    const key = keys[i]
    if (!isPlainObject(cur[key])) cur[key] = {}
    cur = cur[key] as Record<string, unknown>
  }
  cur[keys[keys.length - 1]] = clonePlain(value)
  return root
}

/** Return a new object with `path` removed (deep, immutable, plain). */
export function unsetByPath(obj: unknown, path: string): Record<string, unknown> {
  const root = clonePlain(isPlainObject(obj) ? obj : {})
  const keys = keysOf(path)
  if (keys.length === 0) return root

  let cur = root
  for (let i = 0; i < keys.length - 1; i++) {
    const key = keys[i]
    if (!isPlainObject(cur[key])) return root
    cur = cur[key] as Record<string, unknown>
  }
  delete cur[keys[keys.length - 1]]
  return root
}
