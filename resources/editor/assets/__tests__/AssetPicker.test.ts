// Phase 26.20 — Asset Source Picker: contract, preview/URL-safety, client, store, components,
// and seam integration. Verifies the picker stores a plain URL, blocks unsafe URLs, never
// mutates inputs, and returns the existing `{ src, alt? }` result shape.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'

import {
  mediaAsset,
  externalAsset,
  isAssetReference,
  toMediaResult,
} from '../contracts/AssetReference'
import { resolveAssetPreview, isSafeAssetUrl } from '../runtime/resolveAssetPreview'
import { searchMediaLibrary } from '../mediaLibraryClient'
import { useAssetPickerStore } from '../assetPickerStore'
import { registerMediaPicker, resolveMediaPicker, hasMediaPicker } from '../../components/inspector/mediaPicker'
import AssetPreview from '../components/AssetPreview.vue'
import ExternalUrlInput from '../components/ExternalUrlInput.vue'
import AssetSourcePicker from '../components/AssetSourcePicker.vue'

beforeEach(() => setActivePinia(createPinia()))
afterEach(() => {
  registerMediaPicker(null) // never leak a registered picker into other tests
  vi.restoreAllMocks()
})

// ---------------------------------------------------------------------------
// AssetReference contract
// ---------------------------------------------------------------------------
describe('AssetReference', () => {
  it('builds media/external references and guards them', () => {
    const m = mediaAsset('/uploads/a.jpg', { id: 7, alt: 'A' })
    expect(m).toEqual({ kind: 'media', src: '/uploads/a.jpg', alt: 'A', id: 7 })
    const e = externalAsset('https://x.test/b.png')
    expect(e).toEqual({ kind: 'external', src: 'https://x.test/b.png' })
    expect(isAssetReference(m)).toBe(true)
    expect(isAssetReference({ src: 'x' })).toBe(false)
    expect(isAssetReference('x')).toBe(false)
  })

  it('toMediaResult drops kind/id and keeps only src + non-empty alt', () => {
    expect(toMediaResult(mediaAsset('/u/a.jpg', { id: 9, alt: ' Hi ' }))).toEqual({ src: '/u/a.jpg', alt: 'Hi' })
    expect(toMediaResult(externalAsset('https://x/b.png'))).toEqual({ src: 'https://x/b.png' })
  })
})

// ---------------------------------------------------------------------------
// URL safety + preview (pure, non-mutating)
// ---------------------------------------------------------------------------
describe('resolveAssetPreview + isSafeAssetUrl', () => {
  it('accepts http/https/relative and rejects unsafe schemes', () => {
    expect(isSafeAssetUrl('https://x/a.jpg')).toBe(true)
    expect(isSafeAssetUrl('http://x/a.jpg')).toBe(true)
    expect(isSafeAssetUrl('/uploads/a.jpg')).toBe(true)
    expect(isSafeAssetUrl('uploads/a.jpg')).toBe(true)
    expect(isSafeAssetUrl('//cdn/x.jpg')).toBe(true)
    expect(isSafeAssetUrl('')).toBe(false)
    expect(isSafeAssetUrl('javascript:alert(1)')).toBe(false)
    expect(isSafeAssetUrl('data:image/png;base64,AAA')).toBe(false)
    expect(isSafeAssetUrl(' JavaScript:alert(1)')).toBe(false)
    expect(isSafeAssetUrl('ftp://x/a.jpg')).toBe(false)
  })

  it('resolves string / object / reference values', () => {
    expect(resolveAssetPreview('')).toMatchObject({ empty: true, safe: true, src: '' })
    expect(resolveAssetPreview('/u/a.jpg')).toMatchObject({ empty: false, safe: true, src: '/u/a.jpg' })
    expect(resolveAssetPreview({ src: '/u/b.jpg', alt: 'B' })).toMatchObject({ src: '/u/b.jpg', alt: 'B' })
    expect(resolveAssetPreview(mediaAsset('/u/c.jpg', { id: 1 }))).toMatchObject({ src: '/u/c.jpg', kind: 'media' })
    expect(resolveAssetPreview('javascript:alert(1)')).toMatchObject({ safe: false, src: '' })
  })

  it('never mutates its input', () => {
    const input = { src: 'javascript:alert(1)', alt: 'x' }
    const snapshot = JSON.stringify(input)
    resolveAssetPreview(input)
    expect(JSON.stringify(input)).toBe(snapshot)
  })
})

// ---------------------------------------------------------------------------
// Media Library client
// ---------------------------------------------------------------------------
describe('searchMediaLibrary', () => {
  it('maps the endpoint response and applies the alt fallback', async () => {
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        data: [
          { id: 1, url: '/u/a.jpg', name: 'A', alt: 'Alt A', width: 10, height: 20 },
          { id: 2, url: '/u/b.jpg', filename: 'b.jpg', alt: '', seo_alt: 'SEO B' },
          { id: 3, url: '' }, // dropped — no url
        ],
      }),
    })) as unknown as typeof fetch

    const items = await searchMediaLibrary({ q: 'x', limit: 25 }, { fetchImpl })
    expect(items).toHaveLength(2)
    expect(items[0]).toEqual({ id: 1, url: '/u/a.jpg', name: 'A', alt: 'Alt A', width: 10, height: 20 })
    expect(items[1]).toMatchObject({ id: 2, name: 'b.jpg', alt: 'SEO B' })
    // The request carried q + limit.
    expect((fetchImpl as unknown as ReturnType<typeof vi.fn>).mock.calls[0][0]).toContain('q=x')
    expect((fetchImpl as unknown as ReturnType<typeof vi.fn>).mock.calls[0][0]).toContain('limit=25')
  })

  it('throws on a non-OK response', async () => {
    const fetchImpl = vi.fn(async () => ({ ok: false, status: 500, json: async () => ({}) })) as unknown as typeof fetch
    await expect(searchMediaLibrary({}, { fetchImpl })).rejects.toThrow(/500/)
  })
})

// ---------------------------------------------------------------------------
// Picker store (imperative promise)
// ---------------------------------------------------------------------------
describe('assetPickerStore', () => {
  it('resolves with the media result on confirm', async () => {
    const store = useAssetPickerStore()
    const pending = store.openPicker({ current: '' })
    expect(store.open).toBe(true)

    store.setDraft(mediaAsset('/u/a.jpg', { id: 3, alt: 'A' }))
    expect(store.canConfirm).toBe(true)
    store.setAlt('Custom alt')
    expect(store.confirm()).toBe(true)

    await expect(pending).resolves.toEqual({ src: '/u/a.jpg', alt: 'Custom alt' })
    expect(store.open).toBe(false)
  })

  it('resolves null on cancel', async () => {
    const store = useAssetPickerStore()
    const pending = store.openPicker()
    store.cancel()
    await expect(pending).resolves.toBeNull()
  })

  it('cannot confirm an empty or unsafe draft', () => {
    const store = useAssetPickerStore()
    store.openPicker()
    expect(store.canConfirm).toBe(false)
    store.setDraft(externalAsset('')) // empty
    expect(store.canConfirm).toBe(false)
    store.draft = { kind: 'external', src: 'javascript:alert(1)' } // force unsafe
    expect(store.confirm()).toBe(false)
  })

  it('opening again cancels the previous pending request', async () => {
    const store = useAssetPickerStore()
    const first = store.openPicker()
    store.openPicker()
    await expect(first).resolves.toBeNull()
  })
})

// ---------------------------------------------------------------------------
// Components
// ---------------------------------------------------------------------------
describe('AssetPreview', () => {
  it('shows the image, blocked, and empty states', async () => {
    const img = mount(AssetPreview, { props: { value: '/u/a.jpg' } })
    expect(img.find('img').exists()).toBe(true)

    const blocked = mount(AssetPreview, { props: { value: 'javascript:alert(1)' } })
    expect(blocked.text()).toContain('Blocked URL')

    const empty = mount(AssetPreview, { props: { value: '' } })
    expect(empty.text()).toContain('No media selected')
  })
})

describe('ExternalUrlInput', () => {
  it('emits an external reference for a safe URL and null for an unsafe one', async () => {
    const wrapper = mount(ExternalUrlInput)
    const input = wrapper.find('input')

    await input.setValue('https://x.test/a.png')
    let emitted = wrapper.emitted('update:draft')!
    expect(emitted.at(-1)).toEqual([externalAsset('https://x.test/a.png')])

    await input.setValue('javascript:alert(1)')
    emitted = wrapper.emitted('update:draft')!
    expect(emitted.at(-1)).toEqual([null])
    expect(wrapper.text()).toContain('valid http(s) URL')
  })
})

// ---------------------------------------------------------------------------
// Seam integration
// ---------------------------------------------------------------------------
describe('AssetSourcePicker seam integration', () => {
  it('registers into the mediaPicker seam on mount and unregisters on unmount', async () => {
    expect(hasMediaPicker()).toBe(false)
    const wrapper = mount(AssetSourcePicker)
    await flushPromises()
    expect(hasMediaPicker()).toBe(true)

    wrapper.unmount()
    expect(hasMediaPicker()).toBe(false)
  })

  it('a media field can invoke the seam and receive a { src, alt } result', async () => {
    const wrapper = mount(AssetSourcePicker)
    await flushPromises()

    const picker = resolveMediaPicker()!
    const pending = picker({ accept: ['image/*'], current: '' })

    const store = useAssetPickerStore()
    store.setSource('url')
    store.setDraft(externalAsset('https://x.test/hero.jpg'))
    store.setAlt('Hero')
    store.confirm()

    await expect(pending).resolves.toEqual({ src: 'https://x.test/hero.jpg', alt: 'Hero' })
    wrapper.unmount()
  })
})
