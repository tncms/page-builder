import { describe, it, expect, vi } from 'vitest'
import {
  resolveActionIds,
  resolveActions,
  presetDropsColumns,
  type ActionContext,
} from '../actionModel'

// A no-op delegate context; individual tests override the fields they exercise.
function ctx(overrides: Partial<ActionContext>): ActionContext {
  const noop = () => {}
  return {
    kind: 'block',
    componentId: 'x',
    canMoveUp: true,
    canMoveDown: true,
    duplicate: noop,
    copy: noop,
    cut: noop,
    delete: noop,
    moveUp: noop,
    moveDown: noop,
    openOptions: noop,
    openLayoutPopup: noop,
    addColumn: noop,
    addContainer: noop,
    addElement: noop,
    reverseColumns: noop,
    patchData: noop,
    data: {},
    ...overrides,
  }
}

describe('actionModel — type-aware resolution (Phase 26.1)', () => {
  it('resolves the Section action set', () => {
    const ids = resolveActionIds(ctx({ kind: 'section' }))
    expect(ids).toContain('add-container')
    expect(ids).toContain('choose-layout')
    expect(ids).toContain('duplicate')
    expect(ids).toContain('copy')
    expect(ids).toContain('cut')
    expect(ids).toContain('delete')
    expect(ids).toContain('move-up')
    expect(ids).toContain('move-down')
    expect(ids).toContain('open-options')
    expect(ids).toContain('save-as-pattern')
    // Not a container/column action.
    expect(ids).not.toContain('add-column')
    expect(ids).not.toContain('column-width')
  })

  it('resolves the Container action set', () => {
    const ids = resolveActionIds(ctx({ kind: 'container' }))
    expect(ids).toContain('change-layout')
    expect(ids).toContain('add-column')
    expect(ids).toContain('reverse-columns')
    expect(ids).toContain('toggle-wrap')
    expect(ids).toContain('align-items')
    expect(ids).toContain('justify-content')
    expect(ids).toContain('duplicate')
    expect(ids).toContain('delete')
    expect(ids).toContain('open-options')
    // Copy/Cut are shared with non-column kinds.
    expect(ids).toContain('copy')
    expect(ids).toContain('cut')
    // Container is not a Section (no Add Container) and not a Column (no width).
    expect(ids).not.toContain('add-container')
    expect(ids).not.toContain('column-width')
  })

  it('resolves the Column action set', () => {
    const ids = resolveActionIds(ctx({ kind: 'column' }))
    expect(ids).toContain('column-width')
    expect(ids).toContain('move-left')
    expect(ids).toContain('move-right')
    expect(ids).toContain('add-element')
    expect(ids).toContain('valign')
    expect(ids).toContain('halign')
    expect(ids).toContain('duplicate')
    expect(ids).toContain('delete')
    expect(ids).toContain('open-options')
    // Column uses move-left/right, not the section move-up/down descriptors.
    expect(ids).not.toContain('move-up')
    expect(ids).not.toContain('move-down')
    expect(ids).not.toContain('add-container')
  })

  it('hides column move-left/right at the ends', () => {
    const first = resolveActionIds(ctx({ kind: 'column', canMoveUp: false, canMoveDown: true }))
    expect(first).not.toContain('move-left')
    expect(first).toContain('move-right')

    const last = resolveActionIds(ctx({ kind: 'column', canMoveUp: true, canMoveDown: false }))
    expect(last).toContain('move-left')
    expect(last).not.toContain('move-right')
  })

  it('renders Save as Pattern as a disabled seam that no-ops', () => {
    const c = ctx({ kind: 'section' })
    const action = resolveActions(c).find((a) => a.id === 'save-as-pattern')
    expect(action).toBeDefined()
    expect(action?.disabled?.(c)).toBe(true)
    expect(action?.disabledHint).toBe('Coming soon')
    // run is a no-op (does not throw / call any delegate).
    expect(() => action?.run(c)).not.toThrow()
  })

  it('dispatches each action through its delegate', () => {
    const openLayoutPopup = vi.fn()
    const addColumn = vi.fn()
    const c = ctx({ kind: 'container', openLayoutPopup, addColumn })
    resolveActions(c).find((a) => a.id === 'change-layout')?.run(c)
    resolveActions(c).find((a) => a.id === 'add-column')?.run(c)
    expect(openLayoutPopup).toHaveBeenCalledTimes(1)
    expect(addColumn).toHaveBeenCalledTimes(1)
  })

  it('presetDropsColumns detects fewer widths than columns', () => {
    const preset = { id: 'p', label: '50/50', widths: ['50', '50'] }
    expect(presetDropsColumns(preset, 3)).toBe(true)
    expect(presetDropsColumns(preset, 2)).toBe(false)
    expect(presetDropsColumns(preset, 1)).toBe(false)
  })

  it('toggle-wrap flips the current wrap value rather than always setting it', () => {
    let patched: Record<string, unknown> | null = null
    const context = ctx({ kind: 'container', data: { wrap: true }, patchData: (d) => (patched = d) })

    resolveActions(context).find((action) => action.id === 'toggle-wrap')!.run(context)

    expect(patched).toEqual({ wrap: false })
  })

  it('align-items cycles container alignment to the next value', () => {
    let patched: Record<string, unknown> | null = null
    const context = ctx({ kind: 'container', data: { align: 'center' }, patchData: (d) => (patched = d) })

    resolveActions(context).find((action) => action.id === 'align-items')!.run(context)

    expect(patched).toEqual({ align: 'end' })
  })

  it('valign cycles column vertical alignment to the next value', () => {
    let patched: Record<string, unknown> | null = null
    const context = ctx({ kind: 'column', data: { valign: 'start' }, patchData: (d) => (patched = d) })

    resolveActions(context).find((action) => action.id === 'valign')!.run(context)

    expect(patched).toEqual({ valign: 'center' })
  })
})
