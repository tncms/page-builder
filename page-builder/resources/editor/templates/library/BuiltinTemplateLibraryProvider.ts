// Phase 26.21 — Built-in template source.
//
// Templates shipped with the CMS / a theme. Seeded via `register()` (empty by default in this
// phase). Source is fixed to `builtin`.

import { StaticTemplateLibraryProvider } from './StaticTemplateLibraryProvider'
import { TEMPLATE_SOURCE } from './templateSource'

export class BuiltinTemplateLibraryProvider extends StaticTemplateLibraryProvider {
  constructor() {
    super(TEMPLATE_SOURCE.builtin)
  }
}
