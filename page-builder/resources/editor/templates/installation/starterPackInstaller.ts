// Phase 26.30 — the pack installer (validation-first adoption).
//
// install(packId):
//   1. Resolve the pack from the Phase 26.28 starter pack registry (missing → rejected).
//   2. Map its source to a Phase 26.24 definition source; validate that EVERY referenced template
//      resolves there (availability). Any missing → rejected, nothing recorded (atomic).
//   3. Record an installation reference in the ledger — never a copy of a definition.
// Re-installing is a safe idempotent no-op. It applies nothing, runs no command, and never touches
// BuilderState — installation makes templates available, it does not place content in the builder.

import { templateStarterPackRegistry, type TemplateStarterPackRegistry } from '../starterPacks'
import { templateSourceResolver } from '../source'
import type { TemplateDefinitionProvider } from '../source'
import { installedPackRegistry, InstalledPackRegistry } from './installedPackRegistry'
import {
  packSourceToDefinitionSource,
  type InstallationIssue,
  type InstallationResult,
  type InstalledPack,
  type TemplatePackInstaller,
} from './installationTypes'

export interface StarterPackInstallerDeps {
  packRegistry?: TemplateStarterPackRegistry
  resolver?: TemplateDefinitionProvider
  installed?: InstalledPackRegistry
}

export class StarterPackInstaller implements TemplatePackInstaller {
  private readonly packRegistry: TemplateStarterPackRegistry
  private readonly resolver: TemplateDefinitionProvider
  private readonly installed: InstalledPackRegistry

  constructor(deps: StarterPackInstallerDeps = {}) {
    this.packRegistry = deps.packRegistry ?? templateStarterPackRegistry
    this.resolver = deps.resolver ?? templateSourceResolver
    this.installed = deps.installed ?? installedPackRegistry
  }

  install(packId: string): InstallationResult {
    const pack = this.packRegistry.find(packId)
    if (!pack) {
      return reject({ code: 'pack-not-found', message: `Starter pack "${packId}" is not registered.` })
    }

    const source = pack.source ?? 'builtin'
    const installId = `${source}:${pack.id}`
    if (this.installed.has(installId)) {
      return { ok: true, installed: this.installed.find(installId) ?? null, alreadyInstalled: true, reasons: [] }
    }

    const definitionSource = packSourceToDefinitionSource(source)
    if (!definitionSource) {
      return reject({ code: 'unsupported-source', message: `Pack source "${source}" has no template source to install from.` })
    }

    // Availability: every referenced template must resolve in its source (atomic — all or nothing).
    const missing: string[] = []
    for (const templateId of pack.templates) {
      let definition = null
      try {
        definition = this.resolver.getDefinition(templateId, definitionSource)
      } catch {
        definition = null
      }
      if (!definition) missing.push(templateId)
    }
    if (missing.length > 0) {
      return {
        ok: false,
        installed: null,
        alreadyInstalled: false,
        reasons: missing.map((id) => ({ code: 'template-unavailable', message: `Referenced template "${id}" is not available.` })),
      }
    }

    const record: InstalledPack = {
      id: installId,
      packId: pack.id,
      source,
      name: pack.name,
      category: pack.category,
      templateIds: [...pack.templates],
    }
    return { ok: true, installed: this.installed.register(record), alreadyInstalled: false, reasons: [] }
  }
}

function reject(issue: InstallationIssue): InstallationResult {
  return { ok: false, installed: null, alreadyInstalled: false, reasons: [issue] }
}

/** The default pack installer (default registries + resolver). */
export const starterPackInstaller = new StarterPackInstaller()
