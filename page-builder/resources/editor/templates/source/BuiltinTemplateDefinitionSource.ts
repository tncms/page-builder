// Phase 26.24 — Built-in template definitions (source = 'builtin').
//
// In-memory definitions shipped with the CMS / a theme, seeded via `register`. Empty by default
// in this phase; a later phase (or a theme) populates it. No marketplace, no downloads.

import { StaticTemplateDefinitionSource } from './StaticTemplateDefinitionSource'
import { TEMPLATE_SOURCE } from '../library'

export class BuiltinTemplateDefinitionSource extends StaticTemplateDefinitionSource {
  constructor() {
    super(TEMPLATE_SOURCE.builtin)
  }
}
