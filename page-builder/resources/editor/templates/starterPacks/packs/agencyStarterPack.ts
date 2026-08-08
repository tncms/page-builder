// Phase 26.28 — bundled starter pack: agency-starter-pack.
//
// A leaner grouping of built-in templates (Phase 26.25) for an agency landing page.

import type { TemplateStarterPack } from '../starterPackTypes'

export const agencyStarterPack: TemplateStarterPack = {
  id: 'agency-starter-pack',
  name: 'Agency Starter',
  version: '1.0.0',
  description: 'A focused hero + feature grid for an agency landing page.',
  category: 'agency',
  source: 'builtin',
  templates: ['hero-basic', 'feature-grid-basic'],
  compatibility: { builder: '>=1.0' },
}
