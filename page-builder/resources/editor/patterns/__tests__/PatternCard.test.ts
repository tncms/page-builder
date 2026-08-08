// HOTFIX (Part B, §13/§15) — the pattern card must survive a failing thumbnail
// request: it uses the thumbnail endpoint, falls back to a local placeholder
// exactly once on error (no retry, no broken-image icon), and the pattern stays
// fully insertable.
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PatternCard from '../PatternCard.vue'
import type { PatternManifest } from '../types'

function manifest(over: Partial<PatternManifest> = {}): PatternManifest {
  return {
    id: 'faq.basic', name: 'Basic FAQ', slug: 'basic-faq', category: 'faq', description: 'A FAQ',
    tags: [], version: '1.0.0', author: '', thumbnail: 'preview.svg',
    thumbnail_url: '/api/page-builder/patterns/faq.basic/thumbnail', keywords: [],
    difficulty: 'beginner', featured: false, premium: false, previewWidth: 1280, previewHeight: 720,
    variables: [],
    ...over,
  } as PatternManifest
}

function mountCard(over: Partial<PatternManifest> = {}) {
  return mount(PatternCard, {
    props: { pattern: manifest(over), favorite: false, inserting: false },
  })
}

describe('PatternCard — thumbnail resilience', () => {
  it('renders an <img> pointed at the thumbnail endpoint', () => {
    const img = mountCard().find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/api/page-builder/patterns/faq.basic/thumbnail')
  })

  it('swaps to the local placeholder exactly once when the thumbnail errors', async () => {
    const wrapper = mountCard()
    expect(wrapper.find('.pb-pattern-card__thumb-placeholder').exists()).toBe(false)

    await wrapper.find('img').trigger('error')

    // The <img> (and thus any further request) is gone; the placeholder shows.
    expect(wrapper.find('img').exists()).toBe(false)
    const placeholder = wrapper.find('.pb-pattern-card__thumb-placeholder')
    expect(placeholder.exists()).toBe(true)
    expect(placeholder.text()).toBe('B') // first letter of "Basic FAQ"
  })

  it('keeps the pattern insertable after a thumbnail failure', async () => {
    const wrapper = mountCard()
    await wrapper.find('img').trigger('error')

    const insert = wrapper.find('.pb-pattern-btn--primary')
    expect(insert.attributes('disabled')).toBeUndefined()
    await insert.trigger('click')
    expect(wrapper.emitted('insert')).toBeTruthy()
  })
})
