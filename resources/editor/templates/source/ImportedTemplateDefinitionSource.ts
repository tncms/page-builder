// Phase 26.24 — Imported template definitions (source = 'imported').
//
// The seam for a FUTURE import/export mechanism. Empty by default — a lookup returns an explicit
// null (definition-not-found), never a fabricated template and never a fallback to another
// source. Import/export itself is NOT built in this phase.

import { StaticTemplateDefinitionSource } from './StaticTemplateDefinitionSource'
import { TEMPLATE_SOURCE } from '../library'

export class ImportedTemplateDefinitionSource extends StaticTemplateDefinitionSource {
  constructor() {
    super(TEMPLATE_SOURCE.imported)
  }
}
