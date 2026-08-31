import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import CanvasSectionNode from '../CanvasSectionNode.vue'
import CanvasBlockNode from '../CanvasBlockNode.vue'
import NavigatorNode from '../navigator/NavigatorNode.vue'
import PropertyInspector from '../inspector/PropertyInspector.vue'
import { useEditorStore } from '../../stores/editor'
import type { ComponentNode, EditorMetadata, LayoutPayload } from '../../types'

// PB-TEMPLATE-REGISTRY-2-D — the Canvas, Navigator and Inspector represent an unknown stored
// node (unregistered type) with a presentation-only placeholder/badge/read-only panel. The node
// stays selectable at its position, its children/subtree are preserved and rendered, and no
// schema form / raw JSON / delete-convert control is introduced. Recognition is the store's
// registry adapter (definitionOf) — no second known-type list.

// Metadata registers ONLY `hero` (section) — `theme-mystery` + `exotic-block` are unknown.
const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', schema: { tabs: [{ name: 't', label: 'T', fields: [{ name: 'title', type: 'text', label: 'Title' }] }] } }],
  blocks: [],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'u-1', type: 'theme-mystery', data: { keep: 'me' },
        blocks: [{ id: 'ub-1', type: 'exotic-block', data: { note: 'nested' } }],
      },
      { id: 'k-1', type: 'hero', data: { title: 'Hi' } },
    ],
  } as unknown as LayoutPayload
}

function store() {
  setActivePinia(createPinia())
  const s = useEditorStore()
  s.currentPayload = payload()
  s.metadata = metadata
  s.boot = { strings: { unknownHeading: 'Component unavailable', unknownBadge: 'Unavailable' } } as never
  return s
}

function sectionNode(s: ReturnType<typeof useEditorStore>, id: string): ComponentNode {
  const find = (nodes: ComponentNode[]): ComponentNode | undefined => {
    for (const n of nodes) {
      if (n.id === id) return n
      const hit = find(n.children)
      if (hit) return hit
    }
    return undefined
  }
  const node = find(s.nodeTree as unknown as ComponentNode[])
  if (!node) throw new Error(`node ${id} not found in tree`)
  return node
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('Canvas placeholder', () => {
  it('renders a placeholder for an unknown section but keeps it selectable and keeps its children', async () => {
    const s = store()
    const wrapper = mount(CanvasSectionNode, { props: { node: sectionNode(s, 'u-1') } })

    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('theme-mystery')
    // The child block is still rendered (subtree preserved), not filtered.
    expect(wrapper.findAllComponents(CanvasBlockNode).length).toBeGreaterThan(0)

    // Selecting still works and targets the ORIGINAL node id (no placeholder substitution).
    await wrapper.trigger('click')
    expect(s.selectedComponentId).toBe('u-1')
  })

  it('renders a placeholder for an unknown nested block', () => {
    const s = store()
    const wrapper = mount(CanvasBlockNode, { props: { node: sectionNode(s, 'ub-1') } })
    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('exotic-block')
  })

  it('does not render a placeholder for a known section', () => {
    const s = store()
    const wrapper = mount(CanvasSectionNode, { props: { node: sectionNode(s, 'k-1') } })
    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(false)
  })
})

describe('Navigator unknown badge', () => {
  it('shows an accessible Unavailable badge and keeps the node in the tree', () => {
    const s = store()
    const wrapper = mount(NavigatorNode, { props: { node: sectionNode(s, 'u-1') } })
    const badge = wrapper.find('[data-testid="navigator-unknown-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.attributes('aria-label')).toBe('Unavailable')
    // The type identifier is still shown; the node is not filtered out.
    expect(wrapper.text()).toContain('theme-mystery')
  })

  it('does not badge a known node', () => {
    const s = store()
    const wrapper = mount(NavigatorNode, { props: { node: sectionNode(s, 'k-1') } })
    expect(wrapper.find('[data-testid="navigator-unknown-badge"]').exists()).toBe(false)
  })
})

describe('Inspector read-only unknown panel', () => {
  it('shows the read-only unknown panel (no schema form) for an unknown selection', () => {
    const s = store()
    s.selectComponent('u-1')
    const wrapper = mount(PropertyInspector)
    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(true)
    // No schema-driven field is rendered for the unknown node.
    expect(wrapper.find('.pb-inspector-field').exists()).toBe(false)
  })

  it('a known → unknown → known selection leaves no stale schema fields and mutates nothing', async () => {
    const s = store()
    const pristine = JSON.parse(JSON.stringify(s.currentPayload))

    s.selectComponent('k-1')
    let wrapper = mount(PropertyInspector)
    expect(wrapper.find('.pb-inspector-field').exists()).toBe(true)
    wrapper.unmount()

    s.selectComponent('u-1')
    wrapper = mount(PropertyInspector)
    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(true)
    expect(wrapper.find('.pb-inspector-field').exists()).toBe(false)
    wrapper.unmount()

    s.selectComponent('k-1')
    wrapper = mount(PropertyInspector)
    expect(wrapper.find('.pb-inspector-field').exists()).toBe(true)
    expect(wrapper.find('[data-testid="unknown-placeholder"]').exists()).toBe(false)

    // Selecting/inspecting never mutated any node.
    expect(s.currentPayload).toEqual(pristine)
  })
})
