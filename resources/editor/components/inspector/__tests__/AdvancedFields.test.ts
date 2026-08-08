// Phase 26.5 — Advanced Property Editor Library.
//
// Editors are independently testable: each is mounted in isolation with a
// `modelValue` (+ `field` when it reads schema metadata) and asserted on the values
// it emits. Also covers registry registration/resolution for the new types, the
// validation rules for composite fields, and the recent-colors / media-picker seams.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

import {
  resolveFieldEditor,
  isFieldEditorRegistered,
  registeredFieldTypes,
} from '../fieldEditorRegistry'
import { validateField, isFieldValid } from '../fieldValidation'
import type { SchemaField } from '../schema'
import '../registerBuiltinFieldEditors'

import { recentColors, pushRecentColor, clearRecentColors } from '../recentColors'
import { registerMediaPicker, resolveMediaPicker, hasMediaPicker } from '../mediaPicker'

import ColorField from '../fields/ColorField.vue'
import SpacingField from '../fields/SpacingField.vue'
import TypographyField from '../fields/TypographyField.vue'
import BorderField from '../fields/BorderField.vue'
import BackgroundField from '../fields/BackgroundField.vue'
import LinkField from '../fields/LinkField.vue'
import MediaField from '../fields/MediaField.vue'

/** Last payload emitted for `event` from a mounted wrapper. */
function lastEmit(wrapper: ReturnType<typeof mount>, event: string): unknown {
  const events = wrapper.emitted(event)
  return events ? events[events.length - 1][0] : undefined
}

// ---- Registry ----------------------------------------------------------------

describe('Property Editor Registry — advanced editors', () => {
  it('registers every advanced editor type', () => {
    for (const type of ['color', 'spacing', 'typography', 'border', 'background', 'link', 'media']) {
      expect(isFieldEditorRegistered(type), `${type} editor`).toBe(true)
    }
  })

  it('resolves media as needing the schema field (for alt/accept/multiple)', () => {
    expect(resolveFieldEditor('media')?.needsField).toBe(true)
    expect(resolveFieldEditor('border')?.needsField).toBeFalsy()
  })

  it('keeps at least the full built-in set registered', () => {
    expect(registeredFieldTypes().length).toBeGreaterThanOrEqual(14)
  })
})

// ---- Color editor ------------------------------------------------------------

describe('ColorField', () => {
  beforeEach(() => clearRecentColors())

  it('binds the value and emits text edits', async () => {
    const wrapper = mount(ColorField, { props: { modelValue: '#abcdef' } })
    const text = wrapper.find('input[type="text"]')
    expect((text.element as HTMLInputElement).value).toBe('#abcdef')
    await text.setValue('#123456')
    expect(lastEmit(wrapper, 'update')).toBe('#123456')
  })

  it('encodes opacity < 100 as an 8-digit hex and 100 as 6-digit', async () => {
    const wrapper = mount(ColorField, { props: { modelValue: '#ff0000' } })
    const range = wrapper.find('input[type="range"]')
    await range.setValue('50')
    expect(lastEmit(wrapper, 'update')).toBe('#ff000080') // round(0.5*255)=128=0x80
  })

  it('hides the opacity control for non-hex tokens', () => {
    const wrapper = mount(ColorField, { props: { modelValue: 'brand.primary' } })
    expect(wrapper.find('input[type="range"]').exists()).toBe(false)
  })

  it('reset emits an empty value and commit', async () => {
    const wrapper = mount(ColorField, { props: { modelValue: '#000000' } })
    await wrapper.find('.pb-inspector-iconbtn').trigger('click')
    expect(lastEmit(wrapper, 'update')).toBe('')
    expect(wrapper.emitted('commit')).toBeTruthy()
  })

  it('recent colors are shared and clickable', async () => {
    pushRecentColor('#ff0000')
    const wrapper = mount(ColorField, { props: { modelValue: '' } })
    const swatch = wrapper.find('.pb-inspector-color__recent-swatch')
    expect(swatch.exists()).toBe(true)
    await swatch.trigger('click')
    expect(lastEmit(wrapper, 'update')).toBe('#ff0000')
  })
})

describe('recentColors module', () => {
  beforeEach(() => clearRecentColors())

  it('keeps most-recent-first, de-duplicated, capped at 8', () => {
    for (let i = 0; i < 10; i++) pushRecentColor(`#00000${i}`.slice(0, 7))
    expect(recentColors.value.length).toBe(8)
    pushRecentColor('#abcabc')
    expect(recentColors.value[0]).toBe('#abcabc')
    pushRecentColor('#abcabc') // dedupe, no growth
    expect(recentColors.value.filter((c) => c === '#abcabc').length).toBe(1)
  })

  it('ignores empty values', () => {
    pushRecentColor('   ')
    expect(recentColors.value.length).toBe(0)
  })
})

// ---- Spacing editor ----------------------------------------------------------

describe('SpacingField', () => {
  it('linked mode writes all four sides at once', async () => {
    const wrapper = mount(SpacingField, { props: { modelValue: {} } })
    await wrapper.findAll('input[type="text"]')[0].setValue('8px')
    expect(lastEmit(wrapper, 'update')).toEqual({ top: '8px', right: '8px', bottom: '8px', left: '8px' })
  })

  it('unlinked mode writes only the edited side', async () => {
    const wrapper = mount(SpacingField, { props: { modelValue: { top: '1px', right: '1px', bottom: '1px', left: '1px' } } })
    await wrapper.find('.pb-inspector-spacing__link').trigger('click') // unlink
    await wrapper.findAll('input[type="text"]')[1].setValue('5px') // right
    expect(lastEmit(wrapper, 'update')).toEqual({ top: '1px', right: '5px', bottom: '1px', left: '1px' })
  })
})

// ---- Typography editor -------------------------------------------------------

describe('TypographyField', () => {
  it('edits a metric field and preserves the rest', async () => {
    const wrapper = mount(TypographyField, { props: { modelValue: { size: '16px' } } })
    await wrapper.findAll('input[type="text"]')[0].setValue('Inter') // family
    expect(lastEmit(wrapper, 'update')).toEqual({ size: '16px', family: 'Inter' })
  })

  it('exposes decoration as a constrained select', async () => {
    const wrapper = mount(TypographyField, { props: { modelValue: {} } })
    const selects = wrapper.findAll('select')
    expect(selects.length).toBe(2) // transform + decoration
    await selects[1].setValue('underline')
    expect(lastEmit(wrapper, 'update')).toEqual({ decoration: 'underline' })
    expect(wrapper.emitted('commit')).toBeTruthy()
  })
})

// ---- Border editor -----------------------------------------------------------

describe('BorderField', () => {
  it('patches individual border properties', async () => {
    const wrapper = mount(BorderField, { props: { modelValue: { width: '1px' } } })
    await wrapper.find('select').setValue('dashed')
    expect(lastEmit(wrapper, 'update')).toEqual({ width: '1px', style: 'dashed' })
  })

  it('edits the border color via the text field', async () => {
    const wrapper = mount(BorderField, { props: { modelValue: {} } })
    const colorText = wrapper.findAll('input[type="text"]').find((i) => i.attributes('placeholder') === '#000000 or token')
    await colorText!.setValue('#334455')
    expect(lastEmit(wrapper, 'update')).toEqual({ color: '#334455' })
  })
})

// ---- Background editor -------------------------------------------------------

describe('BackgroundField', () => {
  it('emits a typed { type:"color", color } value', async () => {
    const wrapper = mount(BackgroundField, { props: { modelValue: {} } })
    await wrapper.find('input[type="text"]').setValue('#fafafa')
    expect(lastEmit(wrapper, 'update')).toEqual({ type: 'color', color: '#fafafa' })
  })

  it('clear emits null and commit', async () => {
    const wrapper = mount(BackgroundField, { props: { modelValue: { type: 'color', color: '#fff' } } })
    await wrapper.find('.pb-inspector-iconbtn').trigger('click')
    expect(lastEmit(wrapper, 'update')).toBeNull()
    expect(wrapper.emitted('commit')).toBeTruthy()
  })
})

// ---- Link editor -------------------------------------------------------------

describe('LinkField', () => {
  it('edits the url and preserves other keys', async () => {
    const wrapper = mount(LinkField, { props: { modelValue: { newTab: true } } })
    await wrapper.find('input[type="text"]').setValue('/about')
    expect(lastEmit(wrapper, 'update')).toEqual({ newTab: true, url: '/about' })
  })

  it('toggles open-in-new-tab and commits', async () => {
    const wrapper = mount(LinkField, { props: { modelValue: { url: '/x' } } })
    await wrapper.find('input[type="checkbox"]').setValue(true)
    expect(lastEmit(wrapper, 'update')).toEqual({ url: '/x', newTab: true })
    expect(wrapper.emitted('commit')).toBeTruthy()
  })
})

// ---- Media editor + picker seam ---------------------------------------------

describe('MediaField', () => {
  afterEach(() => registerMediaPicker(null))

  it('defaults to a plain string value (backward-compatible)', async () => {
    const wrapper = mount(MediaField, { props: { modelValue: 'img/a.jpg' } })
    await wrapper.find('input[type="text"]').setValue('img/b.jpg')
    expect(lastEmit(wrapper, 'update')).toBe('img/b.jpg')
  })

  it('uses an { src, alt } object only when the field opts into alt', async () => {
    const field = { name: 'hero', type: 'media', editor: { alt: true } } as unknown as SchemaField
    const wrapper = mount(MediaField, { props: { modelValue: { src: 'a.jpg', alt: '' }, field } })
    expect(wrapper.find('.pb-inspector-media__alt').exists()).toBe(true)
    const alt = wrapper.findAll('input[type="text"]')[1]
    await alt.setValue('A hero')
    expect(lastEmit(wrapper, 'update')).toEqual({ src: 'a.jpg', alt: 'A hero' })
  })

  it('remove clears the value and commits', async () => {
    const wrapper = mount(MediaField, { props: { modelValue: 'a.jpg' } })
    await wrapper.find('.pb-editor-btn--danger').trigger('click')
    expect(lastEmit(wrapper, 'update')).toBe('')
    expect(wrapper.emitted('commit')).toBeTruthy()
  })

  it('shows the picker button and applies its result when a picker is registered', async () => {
    registerMediaPicker(async () => ({ src: 'picked.jpg' }))
    const wrapper = mount(MediaField, { props: { modelValue: '' } })
    const choose = wrapper.findAll('.pb-editor-btn').find((b) => /choose|replace/i.test(b.text()))
    expect(choose).toBeTruthy()
    await choose!.trigger('click')
    await flushPromises()
    expect(lastEmit(wrapper, 'update')).toBe('picked.jpg')
  })
})

describe('mediaPicker seam', () => {
  afterEach(() => registerMediaPicker(null))

  it('register / resolve / has reflect the current picker', () => {
    expect(hasMediaPicker()).toBe(false)
    const picker = async () => null
    registerMediaPicker(picker)
    expect(hasMediaPicker()).toBe(true)
    expect(resolveMediaPicker()).toBe(picker)
    registerMediaPicker(null)
    expect(hasMediaPicker()).toBe(false)
  })
})

// ---- Validation (composite fields) ------------------------------------------

describe('validation — composite fields', () => {
  const f = (over: Partial<SchemaField>): SchemaField => ({ name: 'x', type: 'text', ...over } as SchemaField)

  it('validates a link required + URL well-formedness on its inner url', () => {
    expect(validateField(f({ type: 'link', required: true }), { url: '' })).toBe('This field is required.')
    expect(validateField(f({ type: 'link' }), { url: 'not a url' })).toBe('Enter a valid URL.')
    expect(isFieldValid(f({ type: 'link' }), { url: '/go' })).toBe(true)
    expect(isFieldValid(f({ type: 'link' }), { url: '', newTab: true })).toBe(true) // optional empty
  })

  it('treats an empty media src as required-empty', () => {
    expect(validateField(f({ type: 'media', required: true }), '')).toBe('This field is required.')
    expect(validateField(f({ type: 'media', required: true }), { src: '' })).toBe('This field is required.')
    expect(isFieldValid(f({ type: 'media', required: true }), { src: 'a.jpg' })).toBe(true)
  })
})
