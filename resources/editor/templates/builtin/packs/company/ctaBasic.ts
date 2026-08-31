// Phase 26.25 — Built-in template: Basic CTA (pack: company-pack).
//
// A compact closing section: a heading and a single call-to-action button.

import type { TemplateDefinition } from '../../../contracts'

export const ctaBasic: TemplateDefinition = {
  id: 'cta-basic',
  name: 'Basic Call To Action',
  description: 'A closing prompt with a single primary action.',
  category: 'cta',
  version: '1.0.0',
  // Real catalog thumbnail (PB-FREE-LIBRARY-DESIGN-1-F): a plugin-owned WebP rendered
  // from this template's canonical payload through the Runtime Preview engine.
  preview: '/vendor/page-builder/img/thumbnails/template-cta-basic.webp',
  layout: {
    kind: 'tree',
    tree: {
      sections: [
        {
          type: 'section',
          // Canonical generic-section spacing (PB-FREE-LIBRARY-DESIGN-1-F): box-model
          // `spacing.padding` replaces the legacy `padding:{top,bottom}` object + section
          // `align` that threw in section.blade. Vertical intent (56px) is preserved exactly.
          data: { spacing: { padding: { top: 56, right: 0, bottom: 56, left: 0 } } },
          blocks: [
            { type: 'heading', data: { level: 'h2', text: 'Ready to get started?' } },
            { type: 'button', data: { label: 'Create your account', url: '#' } },
          ],
        },
      ],
    },
  },
}
