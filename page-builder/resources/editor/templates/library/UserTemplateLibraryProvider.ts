// Phase 26.21 — User template source.
//
// User-authored templates live in the `TemplateProvider` (written by authoring/management). This
// provider DISCOVERS them: it derives metadata items from the provider on demand and drops layout
// trees, proving discovery is separate from — and reads through — the runtime storage seam. It
// never writes; renames/deletes happen through the TemplateProvider and simply surface here.

import { BaseTemplateLibraryProvider } from './BaseTemplateLibraryProvider'
import type { TemplateLibraryItem } from './TemplateLibraryItem'
import type { TemplateProvider } from '../contracts'
import { templateProvider as defaultTemplateProvider } from '../provider'
import { toLibraryItem } from './toLibraryItem'
import { TEMPLATE_SOURCE } from './templateSource'

export class UserTemplateLibraryProvider extends BaseTemplateLibraryProvider {
  constructor(private readonly provider: TemplateProvider = defaultTemplateProvider) {
    super()
  }

  all(): TemplateLibraryItem[] {
    return this.provider.all().map((def) => toLibraryItem(def, TEMPLATE_SOURCE.user))
  }
}
