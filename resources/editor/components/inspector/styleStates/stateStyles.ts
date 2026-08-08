// Phase 26.10 — the additive storage-model helpers (the future-UI seam).
//
// State styles live in an OPTIONAL `StyleStateMap` that sits alongside a component's
// normal data — never inside the normal property value. These two pure helpers are all a
// future state editor needs: read the current value for a state, and produce a NEW map
// for an edit. The editor then commits the new map through `update-component-data` like
// any other property, so one edit = one undo entry (unchanged draft/apply flow).

import type { StyleState, StyleStateMap } from './types'

/** The component's own value for a property in a state; `undefined` when unset. Pure. */
export function readStateProperty(
  states: StyleStateMap | undefined,
  state: StyleState,
  property: string,
): unknown {
  const bag = states?.[state]
  return bag ? bag[property] : undefined
}

/**
 * Immutably set (or clear) a state property, returning a NEW map. Clearing with
 * `undefined`/`null` removes the key, and an emptied state bag is dropped, so clearing
 * the last override collapses the shape back — no orphan keys, no migration.
 */
export function setStateProperty(
  states: StyleStateMap | undefined,
  state: StyleState,
  property: string,
  value: unknown,
): StyleStateMap {
  const next: StyleStateMap = { ...(states ?? {}) }
  const bag: Record<string, unknown> = { ...(next[state] ?? {}) }

  if (value === undefined || value === null) {
    delete bag[property]
  } else {
    bag[property] = value
  }

  if (Object.keys(bag).length === 0) {
    delete next[state]
  } else {
    next[state] = bag
  }

  return next
}
