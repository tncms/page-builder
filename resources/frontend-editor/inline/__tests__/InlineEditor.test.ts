import { describe, it, expect } from 'vitest'
import { InlineEditor } from '../InlineEditor'
import type { InlineTarget } from '../SelectionManager'

function target(part: Partial<InlineTarget> = {}): InlineTarget {
  return {
    componentId: 'cta-1',
    type: 'cta',
    field: 'title',
    selector: '.pb-cta__title',
    multiline: false,
    translatable: true,
    value: 'Old',
    rect: { top: 0, left: 0, width: 100, height: 20 },
    typography: {},
    ...part,
  }
}

describe('InlineEditor', () => {
  it('tracks dirty state against the original value', () => {
    const ed = new InlineEditor(target())
    expect(ed.dirty).toBe(false)
    ed.setValue('New')
    expect(ed.dirty).toBe(true)
    ed.setValue('Old')
    expect(ed.dirty).toBe(false)
  })

  it('writes a translatable field under the active locale, preserving others', () => {
    const ed = new InlineEditor(target({ value: 'Xin chào' }))
    ed.setValue('Hello')

    const data = ed.buildData({ title: { vi: 'Xin chào', en: 'Hi' }, align: 'center' }, 'en')
    expect(data).toEqual({ title: { vi: 'Xin chào', en: 'Hello' }, align: 'center' })
  })

  it('seeds a fresh locale map when the field was empty', () => {
    const ed = new InlineEditor(target())
    ed.setValue('Hello')
    expect(ed.buildData({}, 'vi')).toEqual({ title: { vi: 'Hello' } })
  })

  it('writes a non-translatable field as a plain value', () => {
    const ed = new InlineEditor(target({ field: 'button_url', translatable: false }))
    ed.setValue('/go')
    expect(ed.buildData({ button_url: '/old' }, 'vi')).toEqual({ button_url: '/go' })
  })
})
