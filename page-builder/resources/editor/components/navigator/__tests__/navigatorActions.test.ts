import { describe, it, expect, vi } from 'vitest'
import { resolveNavigatorActions, type NavigatorActionContext } from '../navigatorActions'
import type { ComponentNode } from '../../../types'

// Pure registry tests (Context Menu Action Registry cleanup). No component mount:
// visibility / enablement / disabledReason / execute wiring are asserted directly
// against the registry so the resolution contract is pinned independent of the UI.

function node(): ComponentNode {
  return { id: 'n1', type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [] }
}

function ctx(over: Partial<NavigatorActionContext> = {}): NavigatorActionContext {
  return {
    node: node(),
    store: {} as never,
    locks: {} as never,
    isLocked: false,
    moveInfo: { parentId: 'sec', index: 1, count: 3 },
    canMoveUp: true,
    canMoveDown: true,
    commandLoading: false,
    beginRename: () => {},
    insert: { inside: false, before: false, after: false },
    beginInsert: () => {},
    ...over,
  }
}

const ids = (c: NavigatorActionContext) => resolveNavigatorActions(c).map((a) => a.label && a.id)

describe('navigator action registry — resolution', () => {
  it('resolves the stable visible set in order for an unlocked mid-list node', () => {
    expect(ids(ctx())).toEqual(['rename', 'move-up', 'move-down', 'duplicate', 'copy', 'copy-id', 'cut', 'paste-inside', 'lock', 'delete'])
  })

  it('never surfaces feature-gated relative-paste actions', () => {
    const resolved = ids(ctx({ isLocked: true }))
    expect(resolved).not.toContain('paste-before')
    expect(resolved).not.toContain('paste-after')
  })

  it('hides the insertion actions when no placement is available', () => {
    const resolved = ids(ctx())
    expect(resolved).not.toContain('add-inside')
    expect(resolved).not.toContain('add-before')
    expect(resolved).not.toContain('add-after')
  })
})

describe('navigator action registry — structural insertion (Increment 6)', () => {
  const byId = (c: NavigatorActionContext) => Object.fromEntries(resolveNavigatorActions(c).map((a) => [a.id, a]))

  it('surfaces each insertion action only when that placement is available, right after Rename', () => {
    const resolved = resolveNavigatorActions(ctx({ insert: { inside: true, before: true, after: true } })).map((a) => a.id)
    expect(resolved.slice(0, 4)).toEqual(['rename', 'add-inside', 'add-before', 'add-after'])

    const onlyInside = ids(ctx({ insert: { inside: true, before: false, after: false } }))
    expect(onlyInside).toContain('add-inside')
    expect(onlyInside).not.toContain('add-before')
    expect(onlyInside).not.toContain('add-after')
  })

  it('a locked node disables the visible insertion actions with a reason', () => {
    const m = byId(ctx({ insert: { inside: true, before: true, after: true }, isLocked: true }))
    for (const id of ['add-inside', 'add-before', 'add-after']) {
      expect(m[id].enabled).toBe(false)
      expect(m[id].disabledReason).toBe('Locked')
    }
  })

  it('command-loading disables the insertion actions', () => {
    const m = byId(ctx({ insert: { inside: true, before: true, after: true }, commandLoading: true }))
    expect(m['add-inside'].enabled).toBe(false)
    expect(m['add-inside'].disabledReason).toBe('Working…')
  })

  it('execute hands off to beginInsert with the matching placement', () => {
    const beginInsert = vi.fn()
    const resolved = resolveNavigatorActions(ctx({ insert: { inside: true, before: true, after: true }, beginInsert }))
    resolved.find((a) => a.id === 'add-inside')!.execute()
    expect(beginInsert).toHaveBeenLastCalledWith('inside')
    resolved.find((a) => a.id === 'add-before')!.execute()
    expect(beginInsert).toHaveBeenLastCalledWith('before')
    resolved.find((a) => a.id === 'add-after')!.execute()
    expect(beginInsert).toHaveBeenLastCalledWith('after')
  })
})

describe('navigator action registry — visibility', () => {
  it('shows Lock when unlocked and Unlock when locked (never both)', () => {
    expect(ids(ctx({ isLocked: false }))).toContain('lock')
    expect(ids(ctx({ isLocked: false }))).not.toContain('unlock')
    expect(ids(ctx({ isLocked: true }))).toContain('unlock')
    expect(ids(ctx({ isLocked: true }))).not.toContain('lock')
  })
})

describe('navigator action registry — enabled/disabled', () => {
  const byId = (c: NavigatorActionContext) => Object.fromEntries(resolveNavigatorActions(c).map((a) => [a.id, a]))

  it('disables Move Up at the first boundary and Move Down at the last', () => {
    expect(byId(ctx({ canMoveUp: false }))['move-up'].enabled).toBe(false)
    expect(byId(ctx({ canMoveUp: false }))['move-up'].disabledReason).toBe('Already first')
    expect(byId(ctx({ canMoveDown: false }))['move-down'].enabled).toBe(false)
    expect(byId(ctx({ canMoveDown: false }))['move-down'].disabledReason).toBe('Already last')
  })

  it('command-loading disables command actions but not Copy ID / Lock', () => {
    const m = byId(ctx({ commandLoading: true }))
    expect(m['duplicate'].enabled).toBe(false)
    expect(m['duplicate'].disabledReason).toBe('Working…')
    expect(m['delete'].enabled).toBe(false)
    expect(m['copy-id'].enabled).toBe(true)
    expect(m['lock'].enabled).toBe(true)
  })

  it('a locked node disables mutating actions but keeps copy/copy-id and offers Unlock', () => {
    const m = byId(ctx({ isLocked: true }))
    for (const id of ['rename', 'move-up', 'move-down', 'cut', 'paste-inside', 'delete']) {
      expect(m[id].enabled).toBe(false)
      expect(m[id].disabledReason).toBe('Locked')
    }
    expect(m['copy'].enabled).toBe(true)
    expect(m['copy-id'].enabled).toBe(true)
    expect(m['unlock'].enabled).toBe(true)
  })
})

describe('navigator action registry — execute wiring', () => {
  function mockStore() {
    return {
      moveComponent: vi.fn(),
      duplicateComponent: vi.fn(),
      copyComponent: vi.fn(),
      cutComponent: vi.fn(),
      pasteInto: vi.fn(),
      deleteComponent: vi.fn(),
      owner: { type: 'page', id: '7' },
    }
  }

  it('each action calls the matching store/lock method with the node id', () => {
    const store = mockStore()
    const locks = { toggle: vi.fn() }
    const beginRename = vi.fn()
    const resolved = resolveNavigatorActions(ctx({ store: store as never, locks: locks as never, beginRename }))
    const run = (id: string) => resolved.find((a) => a.id === id)!.execute()

    run('rename')
    expect(beginRename).toHaveBeenCalledTimes(1)
    run('move-up')
    expect(store.moveComponent).toHaveBeenCalledWith('n1', 'sec', 0)
    run('move-down')
    expect(store.moveComponent).toHaveBeenCalledWith('n1', 'sec', 2)
    run('duplicate')
    expect(store.duplicateComponent).toHaveBeenCalledWith('n1')
    run('copy')
    expect(store.copyComponent).toHaveBeenCalledWith('n1')
    run('cut')
    expect(store.cutComponent).toHaveBeenCalledWith('n1')
    run('paste-inside')
    expect(store.pasteInto).toHaveBeenCalledWith('n1')
    run('delete')
    expect(store.deleteComponent).toHaveBeenCalledWith('n1')
    run('lock')
    expect(locks.toggle).toHaveBeenCalledWith({ type: 'page', id: '7' }, 'n1')
  })
})
