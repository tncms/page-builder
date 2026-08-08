// Phase 26.31 — the Adoption Discovery Layer.
//
// Projects the Phase 26.30 installed pack ledger and joins it to the existing Template Library (the
// discovery foundation) so a site can browse the templates it has ADOPTED, with pack provenance and
// an availability indicator. Read-only and reference-only: it creates no templates, duplicates no
// definitions, runs no command, and never touches BuilderState or the runtime. Reads are live —
// newly installed packs surface immediately.

import { installedPackRegistry, type InstalledPackRegistry } from '../installation'
import { templateLibrary, type TemplateLibraryProvider } from '../library'
import { projectInstalledTemplates, type InstalledTemplateItem } from './installedTemplateItem'
import { toAdoptionItem, type AdoptionTemplateItem } from './adoptionTemplateItem'
import {
  filterAdoptionByPack,
  filterAvailableAdoption,
  findAdoptionItem,
} from './adoptionFilters'

export class AdoptionDiscoveryProvider {
  constructor(
    private readonly installed: InstalledPackRegistry = installedPackRegistry,
    private readonly library: TemplateLibraryProvider = templateLibrary,
  ) {}

  /** The raw installed-template projection (references only, deduped). */
  installedTemplates(): InstalledTemplateItem[] {
    return projectInstalledTemplates(this.installed.all())
  }

  /** Every adopted template, enriched with library metadata + availability. */
  all(): AdoptionTemplateItem[] {
    return this.installedTemplates().map((item) => toAdoptionItem(item, this.library))
  }

  /** One adopted template by its library-wide id, or undefined. */
  find(id: string): AdoptionTemplateItem | undefined {
    return findAdoptionItem(this.all(), id)
  }

  /** Adopted templates that came from a specific pack (pack-relationship view). */
  fromPack(packId: string): AdoptionTemplateItem[] {
    return filterAdoptionByPack(this.all(), packId)
  }

  /** Adopted templates that currently resolve in the Template Library. */
  available(): AdoptionTemplateItem[] {
    return filterAvailableAdoption(this.all())
  }
}

/** The active adoption discovery provider (projects the default ledger over the default library). */
export const adoptionDiscovery = new AdoptionDiscoveryProvider()
