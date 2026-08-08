// Phase 26.47F — Builder Resource Inspector: open/close, Esc/Enter, prev/next,
// metadata (hide-empty, badges, compatibility), availability, and that Insert
// reuses the existing pipeline via an @insert emit (the Inspector owns no
// insertion logic). Preview reuses the Runtime Preview route (iframe src).
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BuilderResourceInspector from '../BuilderResourceInspector.vue'
import type { BuilderResourceItem } from '../../types'

function item(over: Partial<BuilderResourceItem> = {}): BuilderResourceItem {
  return {
    rid: 'builtin:section:hero',
    kind: 'section',
    name: 'Hero',
    category: 'hero',
    tags: ['landing'],
    source: 'builtin',
    group: 'builtin',
    version: '1.0.0',
    compatibility: { schemaVersion: '1' },
    preview: null,
    available: true,
    locked: false,
    requiredCapability: null,
    ...over,
  }
}

// previewBase defaults to '' so the iframe is not rendered (no real network load
// in happy-dom); the dedicated preview test passes a real base.
function mountInspector(items: BuilderResourceItem[], index = 0, previewBase = '') {
  return mount(BuilderResourceInspector, {
    props: { items, index, previewBase },
    attachTo: document.body,
  })
}

describe('BuilderResourceInspector (Phase 26.47F)', () => {
  it('opens as an accessible dialog with the resource name', () => {
    const w = mountInspector([item()])
    const dialog = w.get('[data-testid="builder-resource-inspector"]')
    expect(dialog.attributes('role')).toBe('dialog')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(w.get('[data-testid="inspector-title"]').text()).toBe('Hero')
  })

  it('reuses the Runtime Preview route for the iframe src (rid-keyed)', () => {
    const w = mountInspector([item()], 0, 'https://cms.test/page-builder/preview')
    const src = w.get('[data-testid="preview-frame"]').attributes('src') ?? ''
    expect(src).toContain('/page-builder/preview')
    expect(src).toContain('source=rid')
    expect(src).toContain('ref=builtin%3Asection%3Ahero')
  })

  it('emits close on the close button and on Escape', async () => {
    const w = mountInspector([item()])
    await w.get('[data-testid="close"]').trigger('click')
    expect(w.emitted('close')).toHaveLength(1)

    await w.get('.pb-ri__drawer').trigger('keydown', { key: 'Escape' })
    expect(w.emitted('close')).toHaveLength(2)
  })

  it('inserts on Enter (and via the Insert button) when available', async () => {
    const w = mountInspector([item()])
    await w.get('.pb-ri__drawer').trigger('keydown', { key: 'Enter' })
    expect(w.emitted('insert')?.[0]?.[0]).toMatchObject({ rid: 'builtin:section:hero' })

    await w.get('[data-testid="insert"]').trigger('click')
    expect(w.emitted('insert')).toHaveLength(2)
  })

  it('navigates prev/next within the group', async () => {
    const items = [item(), item({ rid: 'builtin:section:cta', name: 'CTA' })]
    const w = mountInspector(items, 0)

    expect(w.get('[data-testid="prev"]').attributes('disabled')).toBeDefined()
    await w.get('[data-testid="next"]').trigger('click')
    expect(w.emitted('update:index')?.[0]?.[0]).toBe(1)

    // ArrowRight also navigates.
    await w.get('.pb-ri__drawer').trigger('keydown', { key: 'ArrowRight' })
    expect(w.emitted('update:index')?.[1]?.[0]).toBe(1)
  })

  it('respects availability — a locked resource cannot be inserted', async () => {
    const w = mountInspector([item({ locked: true, requiredCapability: 'page-builder.pro' })])
    expect(w.find('[data-testid="insert"]').exists()).toBe(false)
    expect(w.get('[data-testid="insert-locked"]').attributes('disabled')).toBeDefined()

    // Enter must not insert a locked resource.
    await w.get('.pb-ri__drawer').trigger('keydown', { key: 'Enter' })
    expect(w.emitted('insert')).toBeUndefined()
  })

  it('renders type + source badges and compatibility', () => {
    const w = mountInspector([item()])
    expect(w.get('[data-testid="type-badge"]').text()).toBe('SECTION')
    expect(w.get('[data-testid="source-badge"]').text()).toBe('Built-in')
    expect(w.get('[data-testid="compatibility"]').attributes('data-status')).toBe('compatible')
  })

  it('hides empty metadata fields (no description / no tags) and never shows the RID', () => {
    const w = mountInspector([item({ tags: [], description: null })])
    expect(w.find('[data-testid="meta-description"]').exists()).toBe(false)
    expect(w.find('[data-testid="meta-tags"]').exists()).toBe(false)
    expect(w.get('[data-testid="meta-category"]').text()).toBe('hero')
    // The RID is never shown as visible text (it only appears url-encoded in the
    // preview iframe src attribute, which .text() excludes).
    expect(w.text()).not.toContain('builtin:section:hero')
  })

  it('shows compatibility Unknown when no metadata is present', () => {
    const w = mountInspector([item({ compatibility: null })])
    expect(w.get('[data-testid="compatibility"]').attributes('data-status')).toBe('unknown')
  })
})
