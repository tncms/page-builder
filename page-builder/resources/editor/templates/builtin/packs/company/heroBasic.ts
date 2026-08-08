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
  layout: {
    kind: 'tree',
    tree: {
      sections: [
        {
          type: 'section',
          data: { align: 'center', padding: { top: 64, bottom: 64 } },
          blocks: [
            { type: 'heading', data: { level: 1, text: 'Build something people love' } },
            { type: 'text', data: { text: 'A short, benefit-driven subheading that supports the headline.' } },
            { type: 'button', data: { label: 'Get started', href: '#' } },
          ],
        },
      ],
    },
  },
}
