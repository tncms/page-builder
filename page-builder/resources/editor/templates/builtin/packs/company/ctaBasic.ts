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
  layout: {
    kind: 'tree',
    tree: {
      sections: [
        {
          type: 'section',
          data: { align: 'center', padding: { top: 56, bottom: 56 } },
          blocks: [
            { type: 'heading', data: { level: 2, text: 'Ready to get started?' } },
            { type: 'button', data: { label: 'Create your account', href: '#' } },
          ],
        },
      ],
    },
  },
}
