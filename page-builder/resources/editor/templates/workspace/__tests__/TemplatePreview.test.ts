// Phase 26.36 — template preview foundation: metadata-only, safe URLs, open/close, no execution.
// Preview never applies, resolves, or dispatches a command — "Use" hands off to the usage flow.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import {
  builtinTemplateLibrary,
  type TemplateLibraryItem,
} from '../../library'
import { useTemplateApplyStore } from '../../apply'
import {
  useTemplateWorkspaceStore,
  toTemplatePreview,
  previewImageUrl,
  TemplateLibraryWorkspace,
  WorkspaceTemplateCard,
  TemplatePreviewModal,
} from '..'

function libItem(over: Partial<TemplateLibraryItem> & { templateId: string }): TemplateLibraryItem {
  return { id: `builtin:${over.templateId}`, name: `Name ${over.templateId}`, category: 'hero', source: 'builtin', ...over }
}

beforeEach(() => {
  setActivePinia(createPinia())
  builtinTemplateLibrary.clear()
})
afterEach(() => builtinTemplateLibrary.clear())

describe('preview projection + safe URLs', () => {
  it('keeps only safe URLs and prefers previewUrl over thumbnail', () => {
    const item = libItem({
      templateId: 'hero',
      thumbnail: 'https://cdn.example.com/t.webp',
      previewUrl: 'https://cdn.example.com/p.webp',
    })
    expect(toTemplatePreview(item)).toEqual({
      thumbnail: 'https://cdn.example.com/t.webp',
      previewUrl: 'https://cdn.example.com/p.webp',
    })
    expect(previewImageUrl(item)).toBe('https://cdn.example.com/p.webp') // previewUrl wins
    expect(previewImageUrl(libItem({ templateId: 'x', thumbnail: 'https://cdn.example.com/t.webp' }))).toBe(
      'https://cdn.example.com/t.webp', // falls back to thumbnail
    )
  })

  it('drops unsafe URLs (never surfaced)', () => {
    const unsafe = libItem({ templateId: 'x', thumbnail: 'javascript:alert(1)', previewUrl: 'javascript:alert(2)' })
    expect(toTemplatePreview(unsafe)).toEqual({})
    expect(previewImageUrl(unsafe)).toBeUndefined()
  })

  it('sanitizes previewUrl at registration (unsafe stripped, safe kept)', () => {
    expect(builtinTemplateLibrary.register(libItem({ templateId: 'a', previewUrl: 'https://cdn.example.com/p.webp' })).previewUrl)
      .toBe('https://cdn.example.com/p.webp')
    expect(builtinTemplateLibrary.register(libItem({ templateId: 'b', previewUrl: 'javascript:alert(1)' })).previewUrl)
      .toBeUndefined()
  })
})

describe('preview store', () => {
  it('opens and closes without applying', () => {
    const item = libItem({ templateId: 'hero' })
    const store = useTemplateWorkspaceStore()
    expect(store.previewItem).toBeNull()
    store.preview(item)
    expect(store.previewItem?.id).toBe('builtin:hero')
    // Preview does not apply — the apply flow was never opened.
    expect(useTemplateApplyStore().isOpen).toBe(false)
    store.closePreview()
    expect(store.previewItem).toBeNull()
  })
})

describe('preview UI', () => {
  it('opens the modal from a card, shows the image, and closes', async () => {
    builtinTemplateLibrary.register(libItem({ templateId: 'hero', thumbnail: 'https://cdn.example.com/t.webp' }))
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()

    expect(wrapper.findComponent(TemplatePreviewModal).find('.pb-tpl-preview').exists()).toBe(false)
    await wrapper.findComponent(WorkspaceTemplateCard).find('.pb-tpl-ws-card__preview').trigger('click')

    const modal = wrapper.findComponent(TemplatePreviewModal)
    expect(modal.find('.pb-tpl-preview').exists()).toBe(true)
    expect(modal.find('.pb-tpl-preview__img').attributes('src')).toBe('https://cdn.example.com/t.webp')

    await modal.find('.pb-tpl-preview__close').trigger('click')
    expect(modal.find('.pb-tpl-preview').exists()).toBe(false)
  })

  // Phase 26.39 RC a11y hardening — Escape must dismiss the preview modal (was missing),
  // and the dialog must carry an accessible name.
  it('closes on Escape and exposes an accessible name', async () => {
    builtinTemplateLibrary.register(libItem({ templateId: 'hero', thumbnail: 'https://cdn.example.com/t.webp' }))
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()
    await wrapper.findComponent(WorkspaceTemplateCard).find('.pb-tpl-ws-card__preview').trigger('click')

    const modal = wrapper.findComponent(TemplatePreviewModal)
    expect(modal.find('.pb-tpl-preview').attributes('aria-label')).toContain('Preview')

    await modal.find('.pb-tpl-preview__panel').trigger('keydown.esc')
    expect(modal.find('.pb-tpl-preview').exists()).toBe(false)
  })

  it('renders a placeholder when there is no safe preview image', async () => {
    builtinTemplateLibrary.register(libItem({ templateId: 'hero', thumbnail: 'javascript:alert(1)' }))
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()
    await wrapper.findComponent(WorkspaceTemplateCard).find('.pb-tpl-ws-card__preview').trigger('click')

    const modal = wrapper.findComponent(TemplatePreviewModal)
    expect(modal.find('.pb-tpl-preview__img').exists()).toBe(false)
    expect(modal.find('.pb-tpl-preview__placeholder').exists()).toBe(true)
  })

  it('"Use" from the preview closes it and hands off to the apply flow (no command fired here)', async () => {
    builtinTemplateLibrary.register(libItem({ templateId: 'hero' }))
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()
    await wrapper.findComponent(WorkspaceTemplateCard).find('.pb-tpl-ws-card__preview').trigger('click')

    const modal = wrapper.findComponent(TemplatePreviewModal)
    await modal.find('.pb-tpl-preview__use').trigger('click')

    expect(useTemplateWorkspaceStore().previewItem).toBeNull() // closed
    const apply = useTemplateApplyStore()
    expect(apply.isOpen).toBe(true) // handed off to the existing confirm flow
    expect(apply.lastAppliedId).toBeNull() // but NOT applied
  })
})
