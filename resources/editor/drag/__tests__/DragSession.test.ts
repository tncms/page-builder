import { describe, it, expect } from 'vitest'
import { DragSession } from '../DragSession'
import { invalidResolution, type DropResolution } from '../types'
import { subject } from './helpers'

function resolution(over: Partial<DropResolution> = {}): DropResolution {
  return {
    valid: true,
    reason: null,
    edge: 'before',
    targetId: 'blk2',
    referenceId: 'blk2',
    parentId: 'sec1',
    markerRect: null,
    ...over,
  }
}

describe('DragSession', () => {
  it('is inactive until started', () => {
    const session = new DragSession()
    expect(session.active()).toBe(false)
    expect(session.getSubject()).toBeNull()
  })

  it('tracks subject and pointer on start', () => {
    const session = new DragSession()
    const subj = subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 })
    session.start(subj, 40, 60)
    expect(session.active()).toBe(true)
    expect(session.getSubject()).toBe(subj)
    expect(session.getPointer()).toEqual({ x: 40, y: 60 })
    expect(session.getResolution()).toBeNull()
  })

  it('records pointer and resolution on move', () => {
    const session = new DragSession()
    session.start(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    const res = resolution()
    session.move(50, 120, res)
    expect(session.getPointer()).toEqual({ x: 50, y: 120 })
    expect(session.getResolution()).toBe(res)
  })

  it('ignores move when not active', () => {
    const session = new DragSession()
    session.move(10, 10, resolution())
    expect(session.getResolution()).toBeNull()
  })

  it('drop returns the last resolution and clears the session', () => {
    const session = new DragSession()
    session.start(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    const res = resolution()
    session.move(5, 5, res)
    expect(session.drop()).toBe(res)
    expect(session.active()).toBe(false)
    expect(session.getResolution()).toBeNull()
  })

  it('cancel clears the session with no result', () => {
    const session = new DragSession()
    session.start(subject({ id: 'blk1', kind: 'block' }), 0, 0)
    session.move(5, 5, invalidResolution('x'))
    session.cancel()
    expect(session.active()).toBe(false)
    expect(session.getSubject()).toBeNull()
  })
})
