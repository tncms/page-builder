import { describe, it, expect } from 'vitest'
import { createApplyCoordinator, type ApplyRun } from '../applyCoordinator'

// Deterministic: every run is a manually released deferred; no timers, no wall clock.
function deferredRuns() {
  const log: string[] = []
  const gates: Array<(ok: boolean) => void> = []
  const run = (name: string): ApplyRun => () => {
    log.push('start ' + name)
    return new Promise<boolean>((resolve) => gates.push((ok) => { log.push('end ' + name); resolve(ok) }))
  }
  const settle = async (ok = true) => { gates.shift()!(ok); for (let i = 0; i < 5; i++) await Promise.resolve() }
  return { log, gates, run, settle }
}

describe('applyCoordinator — single serialized write authority', () => {
  it('never runs two at once; coalesces queued requests into one trailing run of the newest', async () => {
    const c = createApplyCoordinator()
    const d = deferredRuns()
    const p1 = c.request(d.run('1'))
    const p2 = c.request(d.run('2'))
    const p3 = c.request(d.run('3'))
    expect(d.gates).toHaveLength(1)
    expect(p2).toBe(p1)
    expect(p3).toBe(p1)
    await d.settle()
    expect(d.log).toEqual(['start 1', 'end 1', 'start 3'])
    await d.settle()
    expect(await p1).toBe(true)
    expect(c.busy).toBe(false)
  })

  it('a request made during the trailing run schedules exactly one more', async () => {
    const c = createApplyCoordinator()
    const d = deferredRuns()
    const p = c.request(d.run('1'))
    c.request(d.run('2'))
    await d.settle()
    c.request(d.run('3'))
    await d.settle()
    await d.settle()
    expect(await p).toBe(true)
    expect(d.log).toEqual(['start 1', 'end 1', 'start 2', 'end 2', 'start 3', 'end 3'])
  })

  it('a request right after the chain settles starts a new chain (no lost request at the boundary)', async () => {
    const c = createApplyCoordinator()
    const d = deferredRuns()
    const p1 = c.request(d.run('1'))
    d.gates.shift()!(true)
    const p2 = p1.then(() => c.request(d.run('2')))
    await Promise.resolve()
    await Promise.resolve()
    await Promise.resolve()
    expect(d.gates).toHaveLength(1)
    d.gates.shift()!(true)
    expect(await p2).toBe(true)
    expect(d.log).toEqual(['start 1', 'end 1', 'start 2', 'end 2'])
  })

  it('a failed or throwing run stops the chain (bounded, no retry) and resolves false', async () => {
    const c = createApplyCoordinator()
    const d = deferredRuns()
    const p = c.request(d.run('1'))
    c.request(d.run('2'))
    await d.settle(false)
    expect(await p).toBe(false)
    expect(d.log).toEqual(['start 1', 'end 1'])
    expect(c.busy).toBe(false)
    expect(await c.request(() => Promise.reject(new Error('boom')))).toBe(false)
  })

  it('dispose drops the queued run and refuses new requests', async () => {
    const c = createApplyCoordinator()
    const d = deferredRuns()
    const p = c.request(d.run('1'))
    c.request(d.run('2'))
    c.dispose()
    await d.settle()
    expect(await p).toBe(false)
    expect(d.log).toEqual(['start 1', 'end 1'])
    expect(await c.request(d.run('3'))).toBe(false)
    expect(d.gates).toHaveLength(0)
  })
})
