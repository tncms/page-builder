// Phase 26.32 — template pack usage workflow: connects adoption discovery to the EXISTING apply
// flow. Intent-only — no resolveTemplate, no command, no BuilderState. An unavailable/unknown
// template never hands off.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { InstalledPackRegistry, installedPackRegistry, type InstalledPack } from '../../installation'
import {
  StaticTemplateLibraryProvider,
  builtinTemplateLibrary,
  type TemplateLibraryItem,
} from '../../library'
import { AdoptionDiscoveryProvider } from '../../adoptionDiscovery'
import type { TemplateSelection } from '../../picker'
import { useTemplateApplyStore } from '../../apply'
import { toUsageSelection, TemplateUsageManager, useTemplateUsageStore } from '..'

function installedPack(over: Partial<InstalledPack> = {}): InstalledPack {
  return {
    id: 'builtin:company',
    packId: 'company',
    source: 'builtin',
    name: 'Company',
    category: 'business',
    templateIds: ['landing-basic'],
    ...over,
  }
}

function libItem(over: Partial<TemplateLibraryItem> & { templateId: string }): TemplateLibraryItem {
  return {
    id: `builtin:${over.templateId}`,
    name: `Name ${over.templateId}`,
    category: 'business',
    source: 'builtin',
    ...over,
  }
}

/** A discovery provider over a fresh ledger + library seeded with the given available template ids. */
function seedDiscovery(available: string[], packOver: Partial<InstalledPack> = {}): AdoptionDiscoveryProvider {
  const library = new StaticTemplateLibraryProvider('builtin')
  for (const templateId of available) library.register(libItem({ templateId }))
  const ledger = new InstalledPackRegistry()
  ledger.register(installedPack(packOver))
  return new AdoptionDiscoveryProvider(ledger, library)
}

describe('toUsageSelection', () => {
  it('rebuilds the library-item selection for an available adopted template', () => {
    const item = seedDiscovery(['landing-basic']).find('builtin:landing-basic')!
    expect(toUsageSelection(item)).toEqual<TemplateSelection>({
      templateId: 'landing-basic',
      source: 'builtin',
      item: {
        id: 'builtin:landing-basic',
        templateId: 'landing-basic',
        name: 'Name landing-basic',
        category: 'business',
        source: 'builtin',
      },
    })
  })

  it('returns null for an unavailable reference (no metadata to hand off)', () => {
    const item = seedDiscovery([]).find('builtin:landing-basic')! // adopted but not in the library
    expect(item.available).toBe(false)
    expect(toUsageSelection(item)).toBeNull()
  })
})

describe('TemplateUsageManager', () => {
  it('connects discovery to the apply-requester for an available template', () => {
    const handed: TemplateSelection[] = []
    const manager = new TemplateUsageManager(seedDiscovery(['landing-basic']), (s) => handed.push(s))

    const result = manager.use('builtin:landing-basic')
    expect(result.ok).toBe(true)
    expect(result.selection?.templateId).toBe('landing-basic')
    expect(handed).toHaveLength(1) // handed off to the existing apply flow
    expect(handed[0].item.name).toBe('Name landing-basic')
  })

  it('rejects an unknown adopted template without handing off', () => {
    const handed: TemplateSelection[] = []
    const manager = new TemplateUsageManager(seedDiscovery(['landing-basic']), (s) => handed.push(s))
    const result = manager.use('builtin:ghost')
    expect(result).toEqual({ ok: false, reason: 'not-found' })
    expect(handed).toHaveLength(0)
  })

  it('rejects an unavailable template without handing off', () => {
    const handed: TemplateSelection[] = []
    const manager = new TemplateUsageManager(seedDiscovery([]), (s) => handed.push(s))
    const result = manager.use('builtin:landing-basic')
    expect(result).toEqual({ ok: false, reason: 'unavailable' })
    expect(handed).toHaveLength(0)
  })

  it('exposes no apply/command/runtime surface — it only connects', () => {
    const manager = new TemplateUsageManager(seedDiscovery(['landing-basic']))
    expect((manager as unknown as Record<string, unknown>).apply).toBeUndefined()
    expect((manager as unknown as Record<string, unknown>).resolveTemplate).toBeUndefined()
    expect((manager as unknown as Record<string, unknown>).execute).toBeUndefined()
  })
})

describe('useTemplateUsageStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    builtinTemplateLibrary.register(libItem({ templateId: 'landing-basic' }))
    installedPackRegistry.register(installedPack())
  })

  afterEach(() => {
    installedPackRegistry.remove('builtin:company')
    builtinTemplateLibrary.remove('builtin:landing-basic')
  })

  it('opens the EXISTING apply confirmation (does not apply)', () => {
    const usage = useTemplateUsageStore()
    const apply = useTemplateApplyStore()

    expect(usage.use('builtin:landing-basic')).toBe(true)
    expect(usage.lastUsedId).toBe('builtin:landing-basic')
    // Handed off to the existing confirm flow — but NOT applied (runtime untouched).
    expect(apply.isOpen).toBe(true)
    expect(apply.pending?.templateId).toBe('landing-basic')
    expect(apply.lastAppliedId).toBeNull()
  })

  it('records a reason for an unknown template and opens nothing', () => {
    const usage = useTemplateUsageStore()
    const apply = useTemplateApplyStore()
    expect(usage.use('builtin:ghost')).toBe(false)
    expect(usage.reason).toBe('not-found')
    expect(apply.isOpen).toBe(false)
  })
})
