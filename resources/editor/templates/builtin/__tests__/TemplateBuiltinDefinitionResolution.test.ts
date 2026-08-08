// Phase 26.25 — built-in packs resolve through the Phase 26.24 source resolver + become discoverable.

import { describe, it, expect, beforeEach } from 'vitest'
import {
  builtinTemplateDefinitionSource,
  templateSourceResolver,
  resolveTemplateDefinition,
} from '../../source'
import { builtinTemplateLibrary } from '../../library'
import { BuiltinTemplatePackRegistry, registerBuiltinPacks } from '..'

beforeEach(() => {
  builtinTemplateDefinitionSource.clear()
  builtinTemplateLibrary.clear()
})

describe('built-in definition resolution', () => {
  it('is not-found before registration (explicit, no fabrication)', () => {
    expect(templateSourceResolver.getDefinition('hero-basic', 'builtin')).toBeNull()
    expect(() => resolveTemplateDefinition('hero-basic', 'builtin')).toThrow(
      expect.objectContaining({ code: 'template_definition_not_found' }),
    )
  })

  it('resolves every bundled template after registration', () => {
    registerBuiltinPacks({ registry: new BuiltinTemplatePackRegistry() })
    expect(resolveTemplateDefinition('hero-basic', 'builtin').name).toBe('Basic Hero')
    expect(resolveTemplateDefinition('feature-grid-basic', 'builtin').name).toBe('Basic Feature Grid')
    expect(resolveTemplateDefinition('cta-basic', 'builtin').name).toBe('Basic Call To Action')
    // Still explicit not-found for an unknown built-in id.
    expect(templateSourceResolver.getDefinition('ghost', 'builtin')).toBeNull()
  })

  it('publishes pack templates to the built-in library for discovery (source = builtin, url-safe)', () => {
    registerBuiltinPacks({ registry: new BuiltinTemplatePackRegistry() })
    const items = builtinTemplateLibrary.all()
    expect(items.map((i) => i.id).sort()).toEqual(['builtin:cta-basic', 'builtin:feature-grid-basic', 'builtin:hero-basic'])
    expect(items.every((i) => i.source === 'builtin')).toBe(true)
    // Metadata only — no layout tree leaks into the library.
    expect((items[0] as Record<string, unknown>).layout).toBeUndefined()
  })

  it('can skip library publishing when asked', () => {
    registerBuiltinPacks({ registry: new BuiltinTemplatePackRegistry(), publishToLibrary: false })
    expect(resolveTemplateDefinition('hero-basic', 'builtin').name).toBe('Basic Hero')
    expect(builtinTemplateLibrary.all()).toHaveLength(0)
  })
})
