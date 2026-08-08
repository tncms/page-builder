// Phase 26.30 — pack installation: adoption records (references only), validation-first, idempotent,
// no definition copies, no runtime coupling.

import { describe, it, expect } from 'vitest'
import { builtinTemplateDefinitionSource, templateSourceResolver } from '../../source'
import { registerBuiltinPacks, BuiltinTemplatePackRegistry } from '../../builtin'
import {
  TemplateStarterPackRegistry,
  companyStarterPack,
  agencyStarterPack,
  type TemplateStarterPack,
} from '../../starterPacks'
import { StarterPackInstaller, InstalledPackRegistry } from '..'

function pack(over: Partial<TemplateStarterPack> = {}): TemplateStarterPack {
  return { id: 'p', name: 'P', version: '1.0.0', category: 'business', templates: ['hero-basic'], ...over }
}

/** Seed the built-in definition source + a fresh pack registry + installer. */
function setup(extraPacks: TemplateStarterPack[] = []) {
  builtinTemplateDefinitionSource.clear()
  registerBuiltinPacks({ registry: new BuiltinTemplatePackRegistry(), publishToLibrary: false }) // hero-basic, feature-grid-basic, cta-basic → builtin source
  const packRegistry = new TemplateStarterPackRegistry()
  packRegistry.register(companyStarterPack)
  packRegistry.register(agencyStarterPack)
  for (const p of extraPacks) packRegistry.register(p)
  const installed = new InstalledPackRegistry()
  const installer = new StarterPackInstaller({ packRegistry, installed })
  return { installer, packRegistry, installed }
}

describe('StarterPackInstaller', () => {
  it('installs a pack as a reference record (no definition copy)', () => {
    const { installer, installed } = setup()
    const result = installer.install('company-starter-pack')
    expect(result).toMatchObject({ ok: true, alreadyInstalled: false })
    expect(result.installed).toMatchObject({
      id: 'builtin:company-starter-pack',
      packId: 'company-starter-pack',
      source: 'builtin',
      templateIds: ['hero-basic', 'feature-grid-basic', 'cta-basic'],
    })
    // References only — no definitions carried on the record.
    expect((result.installed as Record<string, unknown>).templates).toBeUndefined()
    expect(installed.has('builtin:company-starter-pack')).toBe(true)
    // The templates still resolve from their ORIGINAL source — nothing was duplicated.
    expect(templateSourceResolver.getDefinition('hero-basic', 'builtin')?.name).toBe('Basic Hero')
  })

  it('is idempotent — re-installing is a safe no-op', () => {
    const { installer, installed } = setup()
    installer.install('company-starter-pack')
    const again = installer.install('company-starter-pack')
    expect(again).toMatchObject({ ok: true, alreadyInstalled: true })
    expect(installed.all()).toHaveLength(1)
  })

  it('rejects a missing pack', () => {
    const { installer, installed } = setup()
    const result = installer.install('ghost-pack')
    expect(result.ok).toBe(false)
    expect(result.reasons[0].code).toBe('pack-not-found')
    expect(installed.all()).toHaveLength(0)
  })

  it('rejects a pack whose referenced template is unavailable (atomic — nothing recorded)', () => {
    const { installer, installed } = setup([pack({ id: 'broken-pack', templates: ['hero-basic', 'ghost'] })])
    const result = installer.install('broken-pack')
    expect(result.ok).toBe(false)
    expect(result.reasons.some((r) => r.code === 'template-unavailable')).toBe(true)
    expect(installed.has('builtin:broken-pack')).toBe(false)
  })

  it('rejects a pack whose source has no template source', () => {
    const { installer } = setup([pack({ id: 'remote-pack', source: 'marketplace' })])
    const result = installer.install('remote-pack')
    expect(result.ok).toBe(false)
    expect(result.reasons[0].code).toBe('unsupported-source')
  })
})

describe('InstalledPackRegistry', () => {
  it('records references and exposes the available template ids (deduped), immutably', () => {
    const { installer, installed } = setup()
    installer.install('company-starter-pack')
    installer.install('agency-starter-pack') // overlaps hero-basic + feature-grid-basic
    expect(installed.availableTemplateIds().sort()).toEqual(['cta-basic', 'feature-grid-basic', 'hero-basic'])

    const record = installed.find('builtin:company-starter-pack')!
    record.templateIds.push('mutation')
    expect(installed.find('builtin:company-starter-pack')!.templateIds).toEqual(['hero-basic', 'feature-grid-basic', 'cta-basic'])
  })

  it('remove drops the record; referenced templates remain resolvable', () => {
    const { installer, installed } = setup()
    installer.install('company-starter-pack')
    expect(installed.remove('builtin:company-starter-pack')).toBe(true)
    expect(installed.all()).toHaveLength(0)
    expect(templateSourceResolver.getDefinition('hero-basic', 'builtin')?.name).toBe('Basic Hero') // untouched
  })
})
