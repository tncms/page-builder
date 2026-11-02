// The single serialized write authority for Inspector Apply (explicit Apply / Ctrl+Enter, the
// autosave/Save/Publish flush, and the switch dialog all go through it).
//
// - At most ONE apply run is in flight; a request arriving meanwhile never starts a second write.
// - Requests made while a run is active coalesce into exactly ONE trailing run (the newest request
//   wins); a request made during that trailing run schedules one more — repeated only as needed.
// - Every caller awaits the same chain, so it resolves only after the newest requested state ran.
// - A failed run stops the chain (bounded, never auto-retried): the queued intent stays dirty in the
//   Inspector and is recoverable by the next explicit Apply. A thrown run counts as a failure.
// - After dispose() nothing further runs and every pending/future request resolves false.

export type ApplyRun = () => Promise<boolean>

export interface ApplyCoordinator {
  request(run: ApplyRun): Promise<boolean>
  readonly busy: boolean
  dispose(): void
}

export function createApplyCoordinator(): ApplyCoordinator {
  let active: Promise<boolean> | null = null
  let queued: ApplyRun | null = null
  let disposed = false

  async function drain(first: ApplyRun): Promise<boolean> {
    let run: ApplyRun | null = first
    let ok = true
    while (run !== null && !disposed) {
      queued = null
      try {
        ok = await run()
      } catch {
        ok = false
      }
      // Decided synchronously after the run settles, so a request can never slip between the last
      // check and the chain ending: it either becomes the next run here or starts a new chain.
      run = ok ? queued : null
    }
    queued = null
    active = null
    return ok && !disposed
  }

  return {
    request(run: ApplyRun): Promise<boolean> {
      if (disposed) return Promise.resolve(false)
      if (active !== null) {
        queued = run
        return active
      }
      active = drain(run)
      return active
    },
    get busy(): boolean {
      return active !== null
    },
    dispose(): void {
      disposed = true
      queued = null
    },
  }
}
