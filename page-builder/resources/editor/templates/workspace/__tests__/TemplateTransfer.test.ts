// Phase 26.35 — workspace import/export: reuses the Phase 26.26 package + Phase 26.27 compatibility
// gate. Export serializes resolved definitions; import validates atomically (invalid schema /
// incompatible / unsafe / duplicate rejected) and refreshes the workspace. No BuilderState/runtime.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { importedTemplateDefinitionSource } from '../../source'
import {
  importedTemplateLibrary,
  builtinTemplateLibrary,
  userTemplateLibrary,
  toLibraryItem,
  TEMPLATE_SOURCE,
} from '../../library'
import { exportTemplatePackage, serializeTemplatePackage } from '../../package'
import type { TemplateDefinition } from '../../contracts'
import type { PatternTree } from '../../patterns'
import {
  useTemplateWorkspaceStore,
  mountTemplateWorkspace,
  WorkspaceTransferBar,
} from '..'

const validTree: PatternTree = {
  sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: { text: 'Hi' } }] }],
}
function def(id = 'imp-hero', name = 'Imported Hero'): TemplateDefinition {
  return { id, name, category: 'hero', layout: { kind: 'tree', tree: validTree } }
}
function packageJson(defs: TemplateDefinition[], extra: Record<string, unknown> = {}): string {
  return JSON.stringify({ ...exportTemplatePackage(defs, { id: 'kit', name: 'Kit' }), ...extra })
}

function clearAll(): void {
  importedTemplateDefinitionSource.clear()
  importedTemplateLibrary.clear()
  builtinTemplateLibrary.clear()
  userTemplateLibrary.clear?.()
}

beforeEach(() => {
  setActivePinia(createPinia())
  clearAll()
})
afterEach(clearAll)

describe('export', () => {
  it('serializes resolved definitions into a downloadable package', () => {
    importedTemplateDefinitionSource.register(def('imp-hero'))
    importedTemplateLibrary.register(toLibraryItem(def('imp-hero'), TEMPLATE_SOURCE.imported))
    const store = useTemplateWorkspaceStore()
    store.load()

    const result = store.exportResults({ id: 'My Kit', name: 'My Kit' })
    expect(result.ok).toBe(true)
    expect(result.count).toBe(1)
    expect(result.filename).toBe('my-kit.json') // slugified from the id
    expect(JSON.parse(result.json!).templates.map((t: TemplateDefinition) => t.id)).toEqual(['imp-hero'])
    expect(store.exportResult).toBe(result)
  })

  it('rejects when there is nothing to export', () => {
    const store = useTemplateWorkspaceStore()
    store.load()
    expect(store.exportResults({ id: 'x', name: 'X' })).toMatchObject({ ok: false, reason: 'no-templates' })
  })
})

describe('import', () => {
  it('imports a valid package and refreshes the workspace', () => {
    const store = useTemplateWorkspaceStore()
    store.load()
    expect(store.results).toHaveLength(0)

    const outcome = store.importPackage(packageJson([def('imp-a')]))
    expect(outcome.ok).toBe(true)
    expect(outcome.templateIds).toEqual(['imp-a'])
    expect(store.results.some((i) => i.templateId === 'imp-a')).toBe(true) // list refreshed
  })

  it('rejects an invalid schema and registers nothing', () => {
    const store = useTemplateWorkspaceStore()
    const outcome = store.importPackage(packageJson([def('imp-b')], { schema: 'nope' }))
    expect(outcome.ok).toBe(false)
    expect(outcome.error!.issues.length).toBeGreaterThan(0)
    expect(importedTemplateDefinitionSource.getDefinition('imp-b')).toBeNull()
  })

  it('rejects invalid JSON', () => {
    expect(useTemplateWorkspaceStore().importPackage('{ not json').ok).toBe(false)
  })

  it('rejects an incompatible package', () => {
    const outcome = useTemplateWorkspaceStore().importPackage(
      packageJson([def('imp-c')], { compatibility: { builder: '>=2.0' } }),
    )
    expect(outcome.ok).toBe(false)
    expect(importedTemplateDefinitionSource.getDefinition('imp-c')).toBeNull()
  })

  it('rejects a duplicate import', () => {
    const store = useTemplateWorkspaceStore()
    const json = packageJson([def('imp-d')])
    expect(store.importPackage(json).ok).toBe(true)
    const again = store.importPackage(json)
    expect(again.ok).toBe(false)
    expect(again.error!.issues.some((i) => i.code === 'duplicate-registration')).toBe(true)
  })
})

describe('mountTemplateWorkspace', () => {
  it('mounts the workspace into a target element', () => {
    const el = document.createElement('div')
    const app = mountTemplateWorkspace(el)
    expect(app).not.toBeNull()
    expect(el.querySelector('.pb-tpl-workspace')).not.toBeNull()
    app?.unmount()
  })

  it('is a safe no-op for a missing target', () => {
    expect(mountTemplateWorkspace('#does-not-exist')).toBeNull()
  })
})

describe('WorkspaceTransferBar.vue', () => {
  it('shows an error dialog when an import fails', async () => {
    const wrapper = mount(WorkspaceTransferBar)
    useTemplateWorkspaceStore().importPackage('{ not json')
    await nextTick()
    expect(wrapper.find('.pb-tpl-xfer__dialog.is-error').exists()).toBe(true)
  })
})
