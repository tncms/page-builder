import { describe, it, expect, vi } from 'vitest'
import { runModeSwitch, targetModeFor } from '../modeSwitch'

// PB-CORE-AUTHORITY-REMEDIATION-R1 — the render-source switch orchestration.
describe('targetModeFor', () => {
  it('flips between the two sources and treats anything unknown as classic', () => {
    expect(targetModeFor('builder')).toBe('classic')
    expect(targetModeFor('classic')).toBe('builder')
    expect(targetModeFor(null)).toBe('builder')
    expect(targetModeFor('garbage')).toBe('builder')
  })
})

describe('runModeSwitch', () => {
  function io(opts: { dirtyAfterSave?: boolean; switchFails?: boolean } = {}) {
    const calls: string[] = []
    return {
      calls,
      io: {
        save: vi.fn(async () => {
          calls.push('save')
        }),
        stillDirty: () => opts.dirtyAfterSave ?? false,
        switchMode: vi.fn(async (target: string) => {
          calls.push(`switch:${target}`)
          if (opts.switchFails) throw new Error('This page changed in another session.')
        }),
        reload: vi.fn(() => {
          calls.push('reload')
        }),
      },
    }
  }

  it('switches a clean editor without saving or reloading', async () => {
    const { io: x, calls } = io()
    expect(await runModeSwitch('switch', 'classic', x)).toBe(true)
    expect(calls).toEqual(['switch:classic'])
  })

  it('saves first and switches only after a successful save', async () => {
    const { io: x, calls } = io()
    expect(await runModeSwitch('save', 'builder', x)).toBe(true)
    expect(calls).toEqual(['save', 'switch:builder'])
  })

  it('never switches after a failed save', async () => {
    const { io: x, calls } = io({ dirtyAfterSave: true })
    expect(await runModeSwitch('save', 'builder', x)).toBe(false)
    expect(calls).toEqual(['save'])
  })

  it('discards by reloading only after the server switched', async () => {
    const { io: x, calls } = io()
    expect(await runModeSwitch('discard', 'classic', x)).toBe(true)
    expect(calls).toEqual(['switch:classic', 'reload'])
  })

  it('propagates a refusal without reloading, so unsaved work is kept', async () => {
    const { io: x, calls } = io({ switchFails: true })
    await expect(runModeSwitch('discard', 'classic', x)).rejects.toThrow('changed in another session')
    expect(calls).toEqual(['switch:classic'])
  })
})
