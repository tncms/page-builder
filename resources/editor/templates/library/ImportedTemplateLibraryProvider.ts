// Phase 26.21 — Imported template source.
//
// Templates brought in from an export package (the import/export mechanism itself is a FUTURE
// phase — see docs). Seeded via `register()`, empty by default. Source is fixed to `imported`.

import { StaticTemplateLibraryProvider } from './StaticTemplateLibraryProvider'
import { TEMPLATE_SOURCE } from './templateSource'

export class ImportedTemplateLibraryProvider extends StaticTemplateLibraryProvider {
  constructor() {
    super(TEMPLATE_SOURCE.imported)
  }
}
