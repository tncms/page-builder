// Phase 26.25 — Built-in template: Basic Hero (pack: company-pack).
//
// A section with a headline, supporting text, and a call-to-action button. Id-free inline tree
// (server re-keys on apply). Uses only standard component types so it validates and applies
// through the existing runtime.

import type { TemplateDefinition } from '../../../contracts'

export const heroBasic: TemplateDefinition = {
  id: 'hero-basic',
  name: 'Basic Hero',
  description: 'A headline, subtext, and a primary call to action.',
  category: 'hero',
  version: '1.0.0',
  // Real catalog thumbnail (PB-FREE-LIBRARY-DESIGN-1-F): a plugin-owned WebP rendered
  // from this template's canonical payload through the Runtime Preview engine.
  preview: '/vendor/page-builder/img/thumbnails/template-hero-basic.webp',
  layout: {
    kind: 'tree',
    tree: {
      sections: [
        {
          type: 'section',
          // Canonical generic-section spacing (PB-FREE-LIBRARY-DESIGN-1-F): the renderer
          // reads the box-model `spacing.padding.{top,right,bottom,left}`; the legacy
          // `padding:{top,bottom}` object + section-level `align` were non-canonical (they
          // threw in section.blade). Vertical intent (64px) is preserved exactly.
          data: { spacing: { padding: { top: 64, right: 0, bottom: 64, left: 0 } } },
          blocks: [
            { type: 'heading', data: { level: 'h1', text: 'Build something people love' } },
            { type: 'text', data: { text: 'A short, benefit-driven subheading that supports the headline.' } },
            { type: 'button', data: { label: 'Get started', url: '#' } },
          ],
        },
      ],
    },
  },
}
