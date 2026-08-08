// Phase 26.21 — Template Library Foundation.
//
// Discovery layer above TemplateProvider: metadata-first browse/search/filter across sources.
// Verifies provider behavior, source/category filtering, search, immutable metadata, asset URL
// preservation, and — as regression guards — that discovery never carries layout trees and the
// underlying TemplateProvider / Pattern registry stay untouched.

import { describe, it, expect, beforeEach } from 'vitest'

import { localTemplateProvider } from '../../provider'
import { createPatternDefinition, patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'

import {
  TEMPLATE_SOURCE,
  ACTIVE_TEMPLATE_SOURCES,
  isKnownTemplateSource,
  DEFAULT_TEMPLATE_CATEGORY,
  libraryItemId,
  toLibraryItem,
  sanitizeThumbnail,
  BuiltinTemplateLibraryProvider,
  ImportedTemplateLibraryProvider,
  UserTemplateLibraryProvider,
  CompositeTemplateLibraryProvider,
  type TemplateLibraryItem,
} from '..'

function pageTree(): PatternTree {
  return createPatternDefinition({
    name: 'x',
    sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: {} }] }],
  }).tree
}

function tpl(over: Partial<TemplateDefinition> = {}): TemplateDefinition {
  return { id: 't1', name: 'T1', layout: { kind: 'tree', tree: pageTree() }, ...over }
}

function item(over: Partial<TemplateLibraryItem> = {}): TemplateLibraryItem {
  return { id: '', templateId: 'x1', name: 'X', category: 'hero', source: 'ignored', ...over }
}

beforeEach(() => {
  patternLibraryRegistry.clear()
  localTemplateProvider.clear()
})

// ---- source keys ------------------------------------------------------------

describe('template source keys', () => {
  it('exposes active + reserved invariant keys', () => {
    expect(ACTIVE_TEMPLATE_SOURCES).toEqual(['builtin', 'user', 'imported'])
    expect(TEMPLATE_SOURCE.marketplace).toBe('marketplace')
    expect(isKnownTemplateSource('builtin')).toBe(true)
    expect(isKnownTemplateSource('cloud')).toBe(true)
    expect(isKnownTemplateSource('nope')).toBe(false)
  })
})

// ---- metadata projection ----------------------------------------------------

describe('toLibraryItem', () => {
  it('projects metadata only — no layout tree survives', () => {
    const li = toLibraryItem(tpl({ id: 'a', name: 'Home', category: 'landing', description: 'hi' }), TEMPLATE_SOURCE.user)
    expect(li).toMatchObject({
      id: 'user:a',
      templateId: 'a',
      name: 'Home',
      category: 'landing',
      description: 'hi',
      source: 'user',
    })
    expect((li as Record<string, unknown>).layout).toBeUndefined()
    expect((li as Record<string, unknown>).tree).toBeUndefined()
  })

  it('falls back to the default category KEY', () => {
    expect(toLibraryItem(tpl({ category: undefined }), TEMPLATE_SOURCE.user).category).toBe(DEFAULT_TEMPLATE_CATEGORY)
  })

  it('keeps a safe thumbnail URL and drops a dangerous-scheme one', () => {
    expect(toLibraryItem(tpl({ preview: 'https://cdn.example.com/t.webp' }), 'user').thumbnail).toBe(
      'https://cdn.example.com/t.webp',
    )
    // A dangerous scheme is never a portable thumbnail → dropped.
    expect(toLibraryItem(tpl({ preview: 'javascript:alert(1)' }), 'user').thumbnail).toBeUndefined()
  })
})

describe('sanitizeThumbnail', () => {
  // Reuses the Phase 26.20 asset URL boundary: http/https and relative URLs pass; dangerous
  // schemes and blanks are rejected. Templates store URLs by contract, so this guards schemes,
  // not id detection.
  it('passes http/https and relative URLs, rejects dangerous schemes and blanks', () => {
    expect(sanitizeThumbnail('https://x.test/a.png')).toBe('https://x.test/a.png')
    expect(sanitizeThumbnail('/media/a.png')).toBe('/media/a.png')
    expect(sanitizeThumbnail('javascript:alert(1)')).toBeUndefined()
    expect(sanitizeThumbnail('data:image/png;base64,AAAA')).toBeUndefined()
    expect(sanitizeThumbnail('  ')).toBeUndefined()
    expect(sanitizeThumbnail(undefined)).toBeUndefined()
  })
})

// ---- static (builtin / imported) providers ----------------------------------

describe('StaticTemplateLibraryProvider (builtin/imported)', () => {
  it('normalizes source + id on register and fixes its own source', () => {
    const builtin = new BuiltinTemplateLibraryProvider()
    const stored = builtin.register(item({ templateId: 'hero-1', source: 'user' }))
    expect(stored.source).toBe(TEMPLATE_SOURCE.builtin)
    expect(stored.id).toBe(libraryItemId('builtin', 'hero-1'))

    const imported = new ImportedTemplateLibraryProvider()
    expect(imported.register(item({ templateId: 'hero-1' })).source).toBe('imported')
  })

  it('reads immutable clones — mutating a result never leaks into the store', () => {
    const builtin = new BuiltinTemplateLibraryProvider()
    builtin.register(item({ templateId: 'a', tags: ['x'] }))
    const first = builtin.all()[0]
    first.name = 'MUTATED'
    first.tags!.push('y')
    const second = builtin.all()[0]
    expect(second.name).toBe('X')
    expect(second.tags).toEqual(['x'])
  })

  it('supports remove and clear', () => {
    const builtin = new BuiltinTemplateLibraryProvider()
    const stored = builtin.register(item({ templateId: 'a' }))
    expect(builtin.remove(stored.id)).toBe(true)
    expect(builtin.all()).toHaveLength(0)
    builtin.register(item({ templateId: 'b' }))
    builtin.clear()
    expect(builtin.all()).toHaveLength(0)
  })
})

// ---- user provider (derives from TemplateProvider) --------------------------

describe('UserTemplateLibraryProvider', () => {
  it('discovers templates from the TemplateProvider as user-source metadata', () => {
    localTemplateProvider.register(tpl({ id: 'a', name: 'Landing', category: 'landing' }))
    localTemplateProvider.register(tpl({ id: 'b', name: 'Blog' }))
    const user = new UserTemplateLibraryProvider(localTemplateProvider)
    expect(user.all().map((i) => i.id)).toEqual(['user:a', 'user:b'])
    expect(user.all().every((i) => i.source === 'user')).toBe(true)
    expect((user.all()[0] as Record<string, unknown>).layout).toBeUndefined()
  })

  it('reflects TemplateProvider mutations (rename / remove)', () => {
    localTemplateProvider.register(tpl({ id: 'a', name: 'Old' }))
    const user = new UserTemplateLibraryProvider(localTemplateProvider)
    localTemplateProvider.update('a', { name: 'New' })
    expect(user.find('user:a')?.name).toBe('New')
    localTemplateProvider.remove('a')
    expect(user.all()).toHaveLength(0)
  })
})

// ---- composite discovery ----------------------------------------------------

describe('CompositeTemplateLibraryProvider', () => {
  function seeded() {
    const builtin = new BuiltinTemplateLibraryProvider()
    const imported = new ImportedTemplateLibraryProvider()
    const user = new UserTemplateLibraryProvider(localTemplateProvider)
    builtin.register(item({ templateId: 'b1', name: 'Bold Hero', category: 'hero' }))
    imported.register(item({ templateId: 'i1', name: 'Imported Landing', category: 'landing' }))
    localTemplateProvider.register(tpl({ id: 'u1', name: 'User Hero', category: 'hero' }))
    return new CompositeTemplateLibraryProvider([builtin, user, imported])
  }

  it('merges every source in child order', () => {
    expect(seeded().all().map((i) => i.id)).toEqual(['builtin:b1', 'user:u1', 'imported:i1'])
  })

  it('finds one item across sources by library id', () => {
    const c = seeded()
    expect(c.find('imported:i1')?.name).toBe('Imported Landing')
    expect(c.find('missing')).toBeUndefined()
  })

  it('filters by source KEY', () => {
    const c = seeded()
    expect(c.filterBySource('builtin').map((i) => i.id)).toEqual(['builtin:b1'])
    expect(c.filterBySource('user').map((i) => i.id)).toEqual(['user:u1'])
  })

  it('filters by category KEY across sources', () => {
    const c = seeded()
    expect(c.filterByCategory('hero').map((i) => i.id)).toEqual(['builtin:b1', 'user:u1'])
    expect(c.filterByCategory('landing').map((i) => i.id)).toEqual(['imported:i1'])
  })

  it('searches name/description/tags case-insensitively across sources', () => {
    const c = seeded()
    expect(c.search('hero').map((i) => i.id)).toEqual(['builtin:b1', 'user:u1'])
    expect(c.search('  ').map((i) => i.id)).toEqual(['builtin:b1', 'user:u1', 'imported:i1']) // empty → all
    expect(c.search('nomatch')).toHaveLength(0)
  })

  it('searches over tag keys', () => {
    const builtin = new BuiltinTemplateLibraryProvider()
    builtin.register(item({ templateId: 'a', name: 'Plain', tags: ['dark', 'saas'] }))
    const composite = new CompositeTemplateLibraryProvider([builtin])
    expect(composite.search('saas').map((i) => i.id)).toEqual(['builtin:a'])
  })

  it('addProvider extends discovery without redesign (future marketplace seam)', () => {
    const composite = new CompositeTemplateLibraryProvider([])
    expect(composite.all()).toHaveLength(0)
    const extra = new BuiltinTemplateLibraryProvider()
    extra.register(item({ templateId: 'a', name: 'Later' }))
    composite.addProvider(extra)
    expect(composite.all().map((i) => i.name)).toEqual(['Later'])
  })

  it('preserves thumbnail URLs end-to-end (portability)', () => {
    const builtin = new BuiltinTemplateLibraryProvider()
    builtin.register(item({ templateId: 'a', thumbnail: 'https://cdn.example.com/x.webp' }))
    localTemplateProvider.register(tpl({ id: 'u1', preview: 'https://cdn.example.com/u.webp' }))
    const composite = new CompositeTemplateLibraryProvider([builtin, new UserTemplateLibraryProvider(localTemplateProvider)])
    expect(composite.find('builtin:a')?.thumbnail).toBe('https://cdn.example.com/x.webp')
    expect(composite.find('user:u1')?.thumbnail).toBe('https://cdn.example.com/u.webp')
  })
})

// ---- regression: runtime storage untouched ----------------------------------

describe('regression — discovery does not disturb runtime', () => {
  it('leaves the TemplateProvider layout and Pattern registry intact', () => {
    localTemplateProvider.register(tpl({ id: 'a', name: 'A' }))
    const user = new UserTemplateLibraryProvider(localTemplateProvider)
    user.all()
    user.search('a')
    // Full definition (with tree) still resolvable from the provider; discovery read nothing away.
    expect(localTemplateProvider.get('a')?.layout.kind).toBe('tree')
    expect(patternLibraryRegistry.all()).toHaveLength(0)
  })
})
