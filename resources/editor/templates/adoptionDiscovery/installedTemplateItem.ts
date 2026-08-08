// Phase 26.31 — installed template projection (metadata-only, references never definitions).
//
// Projects the Phase 26.30 installed pack ledger into the minimal availability facts a discovery
// surface needs: which templates a site has adopted, from which pack, and in which source. It
// carries ONLY references (ids) — never template trees, component data, runtime state, or package
// contents. This is the "Available Template Projection" step of the adoption discovery flow.

import { packSourceToDefinitionSource, type InstalledPack } from '../installation'

/** A single installed (adopted) template — a reference plus its provenance. Metadata-only. */
export interface InstalledTemplateItem {
  /** References the underlying `TemplateDefinition` id within its source. */
  templateId: string
  /** The invariant Phase 26.24 DEFINITION source KEY the template resolves through. */
  source: string
  /** The `TemplateStarterPack.id` a site adopted the template through (provenance). */
  installedFromPack?: string
}

/**
 * Project installed packs into a deduped list of installed templates. A template referenced by
 * several packs collapses to one item (first pack wins — mirroring the registry's deduped
 * `availableTemplateIds()`); its `installedFromPack` names that first pack. Packs whose source has
 * no definition source (reserved/unknown) are skipped — those are never installable in the first
 * place. Pure: it reads the passed snapshot and never touches a registry or template definition.
 */
export function projectInstalledTemplates(packs: readonly InstalledPack[]): InstalledTemplateItem[] {
  const seen = new Set<string>()
  const items: InstalledTemplateItem[] = []
  for (const pack of packs) {
    const source = packSourceToDefinitionSource(pack.source)
    if (source === null) continue // unsupported source → no definition source → not discoverable
    for (const templateId of pack.templateIds) {
      const key = `${source}:${templateId}`
      if (seen.has(key)) continue
      seen.add(key)
      items.push({ templateId, source, installedFromPack: pack.packId })
    }
  }
  return items
}
