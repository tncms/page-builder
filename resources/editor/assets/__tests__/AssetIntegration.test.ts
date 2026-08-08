// Phase 26.20.1 — Asset Picker integration completion (verification).
//
// Verifies end-to-end that media stays `{ src }`/string with NO ids serialized, across:
//   • Pattern serialization (image.src / logo.src preserved; no id, no runtime picker state)
//   • Template capture (inner image src preserved through resolve)
//   • Template thumbnail (chosen via the SHARED Asset Picker; URL-only on `preview`)
//   • Template rename / duplicate (preview + inner src preserved)
//   • Portability (Builder data round-trips unchanged)

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { serializePattern, createPatternDefinition } from '../../patterns/library'
import { buildTemplateDefinition } from '../../templates/authoring/captureTemplate'
import { useTemplateAuthoringStore } from '../../templates/authoring/templateAuthoringStore'
import { localTemplateProvider } from '../../templates/provider'
import { resolveTemplate } from '../../templates/runtime'
import { renameTemplate, duplicateTemplate } from '../../templates/management/templateActions'
import { useAssetPickerStore } from '../assetPickerStore'
import { externalAsset } from '../contracts/AssetReference'
import { useEditorStore } from '../../stores/editor'
import type { SectionNode, LayoutPayload } from '../../types'

/** A section holding an image block + a logo block with real media src values. */
function mediaSection(id = 'sec-1'): SectionNode {
  return {
    id,
    type: 'section',
    data: {},
    blocks: [
      { id: 'img-1', type: 'image', data: { src: 'https://cdn.test/a.webp', alt: 'A', caption: '' } },
      { id: 'logo-1', type: 'logo', data: { src: '/uploads/logo.png', alt: 'Logo', url: 'https://x.test' } },
    ],
  } as SectionNode
}

/** Recursively assert no numeric `id`-like media reference leaked into serialized data. */
function assertNoMediaId(node: Record<string, unknown>): void {
  const data = (node.data ?? {}) as Record<string, unknown>
  // Media is stored as a URL string under `src`; never a number and never a { kind, id } ref.
  if ('src' in data) {
    expect(typeof data.src).toBe('string')
    expect(data.src).not.toMatch(/^\d+$/) // not a bare numeric id
  }
  expect('kind' in data).toBe(false) // no AssetReference runtime state leaked
  const children = Array.isArray(data.blocks) ? (data.blocks as Record<string, unknown>[]) : []
  children.forEach(assertNoMediaId)
  const blocks = Array.isArray(node.blocks) ? (node.blocks as Record<string, unknown>[]) : []
  blocks.forEach(assertNoMediaId)
}

beforeEach(() => {
  setActivePinia(createPinia())
  localTemplateProvider.clear()
})

// ---------------------------------------------------------------------------
// Pattern serialization
// ---------------------------------------------------------------------------
describe('pattern serialization preserves media src', () => {
  it('keeps image.src and logo.src verbatim, strips ids, leaks no picker state', () => {
    const tree = serializePattern([mediaSection()])
    const section = tree.sections[0]
    const [img, logo] = section.blocks

    expect(img.data.src).toBe('https://cdn.test/a.webp')
    expect(img.data.alt).toBe('A')
    expect(logo.data.src).toBe('/uploads/logo.png')
    expect(logo.data.url).toBe('https://x.test') // logo link untouched
    expect('id' in img).toBe(false) // ids stripped
    assertNoMediaId(section as unknown as Record<string, unknown>) // no numeric id, no kind
  })

  it('a PatternDefinition round-trips the { src, alt } shape (portable JSON)', () => {
    const def = createPatternDefinition({ name: 'P', sections: [mediaSection()] })
    const json = JSON.parse(JSON.stringify(def)) // must survive JSON export
    expect(json.tree.sections[0].blocks[0].data).toEqual({ src: 'https://cdn.test/a.webp', alt: 'A', caption: '' })
  })
})

// ---------------------------------------------------------------------------
// Template capture preserves inner src
// ---------------------------------------------------------------------------
describe('template capture preserves inner media src', () => {
  it('serializes and resolves an image src end-to-end', () => {
    const def = buildTemplateDefinition({ sections: [mediaSection()], name: 'Landing', category: 'page' })
    const resolved = resolveTemplate(def)
    expect(resolved.tree.sections[0].blocks[0].data.src).toBe('https://cdn.test/a.webp')
    assertNoMediaId(resolved.tree.sections[0] as unknown as Record<string, unknown>)
  })
})

// ---------------------------------------------------------------------------
// Template thumbnail via the shared Asset Picker (URL only)
// ---------------------------------------------------------------------------
describe('template thumbnail', () => {
  function seedEditor(): void {
    const editor = useEditorStore()
    editor.currentPayload = { schema: 'pagebuilder/01', sections: [mediaSection('sec-1')] } as LayoutPayload
    editor.selectComponent('sec-1')
  }

  it('picks a thumbnail through the shared picker and stores the URL on preview', async () => {
    seedEditor()
    const authoring = useTemplateAuthoringStore()
    const asset = useAssetPickerStore()
    authoring.openDialog('selection')

    // Drive the shared picker: chooseThumbnail awaits openPicker → confirm resolves it.
    const pending = authoring.chooseThumbnail()
    asset.setDraft(externalAsset('https://cdn.test/thumb.webp'))
    asset.confirm()
    await pending

    expect(authoring.previewUrl).toBe('https://cdn.test/thumb.webp')

    authoring.setName('Hero')
    authoring.setCategory('page')
    expect(authoring.save()).toBe(true)

    const [tpl] = localTemplateProvider.all()
    expect(tpl.preview).toBe('https://cdn.test/thumb.webp') // URL only
    expect(typeof tpl.preview).toBe('string')
  })

  it('clears the thumbnail without leaking any value', () => {
    seedEditor()
    const authoring = useTemplateAuthoringStore()
    authoring.openDialog('selection')
    authoring.setPreviewUrl('https://cdn.test/x.webp')
    authoring.clearThumbnail()
    authoring.setName('NoThumb')
    authoring.setCategory('page')
    authoring.save()
    expect(localTemplateProvider.all()[0].preview).toBeUndefined()
  })
})

// ---------------------------------------------------------------------------
// Rename / duplicate preserve preview + inner src
// ---------------------------------------------------------------------------
describe('rename / duplicate preserve asset references', () => {
  function registerWithThumb(): string {
    const def = buildTemplateDefinition({
      sections: [mediaSection()],
      name: 'Src',
      category: 'page',
      preview: 'https://cdn.test/thumb.webp',
    })
    return localTemplateProvider.register(def).id
  }

  it('rename keeps preview and inner image src', () => {
    const id = registerWithThumb()
    renameTemplate(id, 'Renamed')
    const t = localTemplateProvider.get(id)!
    expect(t.name).toBe('Renamed')
    expect(t.preview).toBe('https://cdn.test/thumb.webp')
    expect((t.layout as { tree: { sections: SectionNode[] } }).tree.sections[0].blocks[0].data.src).toBe('https://cdn.test/a.webp')
  })

  it('duplicate keeps preview and inner image src on an independent copy', () => {
    const id = registerWithThumb()
    const copy = duplicateTemplate(id)!
    expect(copy.id).not.toBe(id)
    expect(copy.preview).toBe('https://cdn.test/thumb.webp')
    const copySrc = (copy.layout as { tree: { sections: SectionNode[] } }).tree.sections[0].blocks[0].data.src
    expect(copySrc).toBe('https://cdn.test/a.webp')
    assertNoMediaId((copy.layout as { tree: { sections: SectionNode[] } }).tree.sections[0] as unknown as Record<string, unknown>)
  })
})
