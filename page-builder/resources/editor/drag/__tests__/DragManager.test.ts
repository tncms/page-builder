import { describe, it, expect, beforeEach } from 'vitest'
import { DragManager } from '../DragManager'
import { DragSession } from '../DragSession'
import { DropManager } from '../DropManager'
import { AutoScroll } from '../AutoScroll'
import type { DragSubject, DropResolution } from '../types'
import { scene, rect, subject, type MarkerSpec } from './helpers'

const TREE: MarkerSpec[] = [
  {
    id: 'sec1',
    kind: 'section',
    type: 'section',
    rect: rect(0, 0, 400, 300),
    children: [
      { id: 'blk1', kind: 'block', rect: rect(10, 10, 380, 60) },
      { id: 'blk2', kind: 'block', rect: rect(10, 80, 380, 60) },
    ],
  },
]

class FakeTarget {
  listeners: Record<string, EventListener[]> = {}
  addEventListener(type: string, cb: EventListener) {
    ;(this.listeners[type] ??= []).push(cb)
  }
  removeEventListener(type: string, cb: EventListener) {
    this.listeners[type] = (this.listeners[type] ?? []).filter((l) => l !== cb)
  }
  emit(type: string, event: unknown) {
    ;(this.listeners[type] ?? []).slice().forEach((l) => l(event as Event))
  }
  count(type: string) {
    return (this.listeners[type] ?? []).length
  }
}

function pointer(x: number, y: number): PointerEvent {
  return { clientX: x, clientY: y, preventDefault() {} } as unknown as PointerEvent
}

function harness() {
  const s = scene(TREE)
  const session = new DragSession()
  const manager = new DragManager(session, new DropManager(s.geometry, s.hitTest), new AutoScroll())
  const target = new FakeTarget()
  // Swap the event target used for global listeners.
  ;(manager as unknown as { target: FakeTarget }).target = target

  const events = { start: 0, moves: [] as DropResolution[], drops: [] as (DropResolution | null)[], cancels: 0 }
  const subj: DragSubject = subject({ id: 'blk1', kind: 'block', parentId: 'sec1', index: 0 })
  manager.bind({
    onStart: () => {
      events.start += 1
    },
    onMove: (_x, _y, resolution) => events.moves.push(resolution),
    onDrop: (resolution) => events.drops.push(resolution),
    onCancel: () => {
      events.cancels += 1
    },
    getViewport: () => s.viewport,
  })

  return { manager, session, target, events, subj }
}

describe('DragManager', () => {
  let h: ReturnType<typeof harness>
  beforeEach(() => {
    h = harness()
  })

  it('starts a drag and begins listening globally', () => {
    h.manager.start(h.subj, pointer(50, 40))
    expect(h.manager.isActive()).toBe(true)
    expect(h.events.start).toBe(1)
    expect(h.target.count('pointermove')).toBe(1)
    expect(h.target.count('pointerup')).toBe(1)
  })

  it('resolves a drop target on move (preview only)', () => {
    h.manager.start(h.subj, pointer(50, 40))
    h.target.emit('pointermove', pointer(200, 90))
    expect(h.events.moves).toHaveLength(1)
    expect(h.events.moves[0]).toMatchObject({ valid: true, targetId: 'blk2' })
    // The session mirrors the latest resolution — no layout change occurred.
    expect(h.session.getResolution()?.targetId).toBe('blk2')
  })

  it('fires onDrop exactly once on pointerup and tears down', () => {
    h.manager.start(h.subj, pointer(50, 40))
    h.target.emit('pointermove', pointer(200, 90))
    h.target.emit('pointerup', pointer(200, 90))
    expect(h.events.drops).toHaveLength(1)
    expect(h.events.drops[0]).toMatchObject({ targetId: 'blk2' })
    expect(h.manager.isActive()).toBe(false)
    expect(h.target.count('pointermove')).toBe(0)
    // A stray move after release is ignored.
    h.target.emit('pointermove', pointer(10, 10))
    expect(h.events.moves).toHaveLength(1)
  })

  it('cancels on Escape and does not drop', () => {
    h.manager.start(h.subj, pointer(50, 40))
    h.target.emit('keydown', { key: 'Escape', preventDefault() {} })
    expect(h.events.cancels).toBe(1)
    expect(h.events.drops).toHaveLength(0)
    expect(h.manager.isActive()).toBe(false)
    expect(h.target.count('keydown')).toBe(0)
  })

  it('ignores start when no callbacks are bound', () => {
    const s = scene(TREE)
    const manager = new DragManager(new DragSession(), new DropManager(s.geometry, s.hitTest), new AutoScroll())
    manager.start(h.subj, pointer(0, 0))
    expect(manager.isActive()).toBe(false)
  })
})
