// Phase 26.27 — versioning & compatibility: version range, version contract, package checks,
// migration seam, and the import gate.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import {
  parseVersion,
  isExactVersion,
  isValidRange,
  satisfiesRange,
  validateTemplateCompatibility,
  checkPackageCompatibility,
  defaultBuilderEnvironment,
  nullMigrationProvider,
  type BuilderEnvironment,
  type TemplateCompatibility,
} from '..'
import { exportTemplatePackage, importTemplatePackage, TemplatePackageError, type TemplatePackage } from '../../package'
import { importedTemplateDefinitionSource } from '../../source'
import { importedTemplateLibrary } from '../../library'
import { patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'

const tree: PatternTree = { sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: {} }] }] }
function def(id = 'imp'): TemplateDefinition {
  return { id, name: 'Imp', layout: { kind: 'tree', tree } }
}
function pkg(compatibility?: TemplatePackage['compatibility']): TemplatePackage {
  return { ...exportTemplatePackage([def()], { id: 'kit', name: 'Kit' }), compatibility }
}

// ---- version range ----------------------------------------------------------

describe('versionRange', () => {
  it('parses and validates versions', () => {
    expect(parseVersion('1.2')).toEqual({ major: 1, minor: 2, patch: 0 })
    expect(parseVersion('x')).toBeNull()
    expect(isExactVersion('1.0.0')).toBe(true)
    expect(isExactVersion('1.0')).toBe(false)
  })

  it('checks ranges', () => {
    expect(satisfiesRange('1.0.0', '>=1.0')).toBe(true)
    expect(satisfiesRange('1.0.0', '>=2.0')).toBe(false)
    expect(satisfiesRange('1.5.0', '>=1.0 <2.0')).toBe(true)
    expect(satisfiesRange('2.0.0', '>=1.0 <2.0')).toBe(false)
    expect(satisfiesRange('1.0.0', '*')).toBe(true)
    expect(isValidRange('>=1.0')).toBe(true)
    expect(isValidRange('garbage')).toBe(false)
  })
})

// ---- version contract -------------------------------------------------------

describe('validateTemplateCompatibility', () => {
  const valid: TemplateCompatibility = { schemaVersion: '1', templateVersion: '1.0.0' }

  it('accepts valid metadata and an absent optional builderVersion', () => {
    expect(validateTemplateCompatibility(valid)).toEqual([])
  })

  it('flags an unknown schema, invalid version, and invalid builder', () => {
    expect(validateTemplateCompatibility({ ...valid, schemaVersion: '9' }).some((i) => i.code === 'unknown-schema')).toBe(true)
    expect(validateTemplateCompatibility({ ...valid, templateVersion: '1.0' }).some((i) => i.code === 'invalid-version')).toBe(true)
    expect(validateTemplateCompatibility({ ...valid, builderVersion: 'nope' }).some((i) => i.code === 'invalid-builder')).toBe(true)
  })
})

// ---- package compatibility --------------------------------------------------

describe('checkPackageCompatibility', () => {
  it('accepts a package with no compatibility block (unconstrained)', () => {
    expect(checkPackageCompatibility(pkg())).toEqual({ compatible: true, reasons: [], warnings: [] })
  })

  it('rejects an unsupported package schema', () => {
    const result = checkPackageCompatibility({ ...pkg(), schema: 'nope' })
    expect(result.compatible).toBe(false)
    expect(result.reasons[0].code).toBe('unsupported-schema')
  })

  it('gates on a builder range', () => {
    expect(checkPackageCompatibility(pkg({ builder: '>=1.0' })).compatible).toBe(true)
    const bad = checkPackageCompatibility(pkg({ builder: '>=2.0' }))
    expect(bad.compatible).toBe(false)
    expect(bad.reasons[0].code).toBe('builder-incompatible')
  })

  it('detects a missing required component against a known set, warns without one', () => {
    const env: BuilderEnvironment = { ...defaultBuilderEnvironment, components: new Set(['heading', 'text']) }
    expect(checkPackageCompatibility(pkg({ requiredComponents: ['heading'] }), env).compatible).toBe(true)
    const missing = checkPackageCompatibility(pkg({ requiredComponents: ['gallery'] }), env)
    expect(missing.compatible).toBe(false)
    expect(missing.reasons[0].code).toBe('missing-component')
    // No known set → cannot verify → non-blocking warning.
    const warned = checkPackageCompatibility(pkg({ requiredComponents: ['gallery'] }))
    expect(warned.compatible).toBe(true)
    expect(warned.warnings[0].code).toBe('components-unverified')
  })
})

// ---- migration seam (contract only) ----------------------------------------

describe('migration seam', () => {
  it('the null provider migrates nothing', () => {
    expect(nullMigrationProvider.canMigrate('1', '2')).toBe(false)
    expect(() => nullMigrationProvider.migrate(pkg())).toThrow()
  })
})

// ---- import gate ------------------------------------------------------------

describe('import compatibility gate', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    patternLibraryRegistry.clear()
    importedTemplateDefinitionSource.clear()
    importedTemplateLibrary.clear()
  })

  it('rejects an incompatible package BEFORE registration', () => {
    expect(() => importTemplatePackage(pkg({ builder: '>=2.0' }))).toThrow(TemplatePackageError)
    expect(importedTemplateDefinitionSource.getDefinition('imp')).toBeNull()
    expect(importedTemplateLibrary.all()).toHaveLength(0)
  })

  it('imports a compatible package and preserves warnings', () => {
    const result = importTemplatePackage(pkg({ requiredComponents: ['gallery'] }))
    expect(result.ok).toBe(true)
    expect(result.warnings.some((w) => w.code === 'components-unverified')).toBe(true)
    expect(importedTemplateDefinitionSource.getDefinition('imp')?.name).toBe('Imp')
  })
})
