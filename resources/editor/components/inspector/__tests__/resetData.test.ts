import { describe, it, expect } from 'vitest'
import {
  buildDefaultData,
  diffFieldPaths,
  editableFieldPaths,
  fieldDefault,
  hasMeaningfulValues,
  preserveStructuralData,
} from '../resetData'
import type { ComponentSchema } from '../schema'

const schema = {
  tabs: [
    {
      name: 'content',
      fields: [
        { name: 'title', type: 'text', translatable: true, default: 'Default title' },
        { name: 'count', type: 'number', default: 5 },
        { name: 'sticky', type: 'toggle' },
        { type: 'group', name: 'g', fields: [{ name: 'pad', type: 'spacing' }] },
      ],
    },
  ],
} as unknown as ComponentSchema

describe('resetData helpers', () => {
  it('editableFieldPaths walks groups', () => {
    expect(editableFieldPaths(schema).sort()).toEqual(['count', 'pad', 'sticky', 'title'])
  })

  it('fieldDefault follows preset → default → type-empty priority', () => {
    const title = schema.tabs[0].fields[0] as any
    const sticky = schema.tabs[0].fields[2] as any
    expect(fieldDefault(title, { title: 'From preset' })).toBe('From preset')
    expect(fieldDefault(title)).toBe('Default title')
    expect(fieldDefault(sticky)).toBe(false) // toggle empty
  })

  it('buildDefaultData resets editable fields, preserving non-schema keys and other locales', () => {
    const current = { title: { vi: 'X', en: 'Y' }, count: 9, sticky: true, pad: { top: '8' }, _internal: 'keep' }
    const out = buildDefaultData({ schema, currentData: current, locale: 'vi' })
    expect(out.title).toEqual({ vi: 'Default title', en: 'Y' }) // other locale preserved
    expect(out.count).toBe(5)
    expect(out.sticky).toBe(false)
    expect(out.pad).toEqual({})
    expect((out as any)._internal).toBe('keep') // non-schema key preserved
  })

  it('buildDefaultData prefers active preset values', () => {
    const out = buildDefaultData({ schema, currentData: {}, locale: 'vi', presetData: { count: 42 } })
    expect(out.count).toBe(42)
    expect(out.title).toEqual({ vi: 'Default title' }) // falls back to field default
  })

  it('diffFieldPaths lists only changed editable paths', () => {
    const applied = { title: { vi: 'X' }, count: 9 }
    const draft = { title: { vi: 'X' }, count: 5 }
    expect(diffFieldPaths(applied, draft, schema)).toEqual(['count'])
  })

  it('preserveStructuralData keeps non-schema keys only', () => {
    const kept = preserveStructuralData({ title: 'x', _ref: 'r', extra: 1 }, schema)
    expect(kept).toEqual({ _ref: 'r', extra: 1 })
  })

  it('hasMeaningfulValues reflects whether editable values exist', () => {
    expect(hasMeaningfulValues({ title: { vi: 'X' } }, schema, 'vi')).toBe(true)
    expect(hasMeaningfulValues({}, schema, 'vi')).toBe(false)
    expect(hasMeaningfulValues({ title: { vi: '' }, count: null, sticky: false }, schema, 'vi')).toBe(false)
  })
})
