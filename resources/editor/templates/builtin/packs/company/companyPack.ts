// Phase 26.25 — Built-in pack: company-pack.
//
// The first first-party template pack: a hero, a feature grid, and a CTA. The manifest lists the
// template ids that must match `definitions` exactly (validation enforces the 1:1). Registered
// into the builtin definition source at boot via `registerBuiltinPacks`.

import type { BuiltinTemplatePack } from '../../builtinPackTypes'
import { heroBasic } from './heroBasic'
import { featureGridBasic } from './featureGridBasic'
import { ctaBasic } from './ctaBasic'

export const companyPack: BuiltinTemplatePack = {
  manifest: {
    id: 'company-pack',
    name: 'Company Pack',
    version: '1.0.0',
    description: 'Starter page sections for a company site: hero, feature grid, and CTA.',
    schema: 'pagebuilder/01',
    templates: ['hero-basic', 'feature-grid-basic', 'cta-basic'],
  },
  definitions: [heroBasic, featureGridBasic, ctaBasic],
}
