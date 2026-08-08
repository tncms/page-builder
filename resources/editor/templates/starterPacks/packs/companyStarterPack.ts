// Phase 26.28 — bundled starter pack: company-starter-pack.
//
// Groups the built-in company templates (Phase 26.25) into a starter a business site can adopt.
// References template ids only — it does not own them.

import type { TemplateStarterPack } from '../starterPackTypes'

export const companyStarterPack: TemplateStarterPack = {
  id: 'company-starter-pack',
  name: 'Company Starter',
  version: '1.0.0',
  description: 'Hero, feature grid, and CTA to launch a company site.',
  category: 'business',
  source: 'builtin',
  templates: ['hero-basic', 'feature-grid-basic', 'cta-basic'],
  compatibility: { builder: '>=1.0' },
}
