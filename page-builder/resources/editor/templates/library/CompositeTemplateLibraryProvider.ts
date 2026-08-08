// Phase 26.21 — Composite discovery across all sources.
//
// Aggregates any number of `TemplateLibraryProvider`s (builtin + user + imported today; a future
// marketplace/remote/cloud provider slots in with NO redesign). `all()` concatenates child
// snapshots in child order; find/search/filter are inherited and run over the merged set, so a
// query spans every source at once. Purely additive: registering a new child is the only change
// a new source requires.

import { BaseTemplateLibraryProvider } from './BaseTemplateLibraryProvider'
import type { TemplateLibraryItem } from './TemplateLibraryItem'
import type { TemplateLibraryProvider } from './TemplateLibraryProvider'

export class CompositeTemplateLibraryProvider extends BaseTemplateLibraryProvider {
  private readonly providers: TemplateLibraryProvider[]

  constructor(providers: TemplateLibraryProvider[] = []) {
    super()
    this.providers = [...providers]
  }

  /** Append a source provider (e.g. a future marketplace). */
  addProvider(provider: TemplateLibraryProvider): void {
    this.providers.push(provider)
  }

  all(): TemplateLibraryItem[] {
    return this.providers.flatMap((provider) => provider.all())
  }
}
