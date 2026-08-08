import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { computed } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import { NavigatorLocksKey } from '../injection'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import type { ComponentNode, LayoutPayload } from '../../../types'

// The registry refactor must leave the rendered menu byte-identical: same labels,
// same order, same disabled states. This pins that contract at the DOM level.
function node(id: string): ComponentNode {
  return { id, type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [] }
}

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec',
        type: 'hero',
        data: {},
        blocks: [
          { id: 'b1', type: 'heading', data: {} },
          { id: 'b2', type: 'heading', data: {} },
          { id: 'b3', type: 'heading', data: {} },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const labels = (w: ReturnType<typeof mount>) => w.findAll('button[role="menuitem"]').map((b) => b.text())
const disabledOf = (w: ReturnType<typeof mount>, label: string) =>
  w.findAll('button[role="menuitem"]').find((b) => b.text() === label)!.attributes('disabled') !== undefined

async function openMenu(w: ReturnType<typeof mount>) {
  await w.find('.pb-editor-navigator-menu-btn').trigger('click')
}

describe('Navigator menu rendering unchanged after registry refactor', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.currentPayload = payload()
  })

  it('renders the same labels in the same order for an unlocked mid-list node', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: node('b2') } })
    await openMenu(wrapper)
    expect(labels(wrapper)).toEqual(['Rename', 'Move Up', 'Move Down', 'Duplicate', 'Copy', 'Copy ID', 'Cut', 'Paste inside', 'Lock', 'Delete'])
    // Mid-list, unlocked → every item enabled.
    for (const l of labels(wrapper)) expect(disabledOf(wrapper, l)).toBe(false)
  })

  it('keeps the danger styling on Delete', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: node('b2') } })
    await openMenu(wrapper)
    const del = wrapper.findAll('button[role="menuitem"]').find((b) => b.text() === 'Delete')!
    expect(del.classes()).toContain('pb-editor-navigator-menu__danger')
  })

  it('a locked node shows Unlock and disables the mutating items', async () => {
    const store = useEditorStore()
    const locks = useNavigatorLocksStore()
    locks.lock(store.owner, 'b2')
    const wrapper = mount(NavigatorNode, {
      props: { node: node('b2') },
      global: { provide: { [NavigatorLocksKey as symbol]: computed(() => locks.lockedSet(store.owner)) } },
    })
    await openMenu(wrapper)
    expect(labels(wrapper)).toContain('Unlock')
    expect(labels(wrapper)).not.toContain('Lock')
    for (const l of ['Move Up', 'Move Down', 'Cut', 'Paste inside', 'Delete']) expect(disabledOf(wrapper, l)).toBe(true)
    for (const l of ['Copy', 'Copy ID', 'Unlock']) expect(disabledOf(wrapper, l)).toBe(false)
  })
})
