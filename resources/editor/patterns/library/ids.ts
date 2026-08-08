// Phase 26.11 — session-unique id generation for the pattern library.
//
// The editor deliberately does NOT invent node ids for real inserts — the server
// assigns them (see `insert-pattern`, which re-keys server-side). These ids are only for
// the optional client-side needs: a stable pattern-definition id, and the defensive
// `regenerateIds` restore path (a standalone valid tree with guaranteed-unique ids).

let counter = 0

/** A session-unique id with the given prefix (monotonic counter + short random). */
export function uniqueId(prefix: string): string {
  counter += 1
  const random = Math.random().toString(36).slice(2, 8)
  return `${prefix}-${counter}-${random}`
}
