// Phase 26.25 — Built-in template: Basic Feature Grid (pack: company-pack).
//
// A section holding a container of three columns, each with a small heading and description.
// Exercises nested layout (section → container → column → elements) that the compatibility
// validator checks via the static nesting policy.

import type { TemplateDefinition } from '../../../contracts'
import type { PatternBlockSnapshot } from '../../../../patterns/library'

function featureColumn(title: string): PatternBlockSnapshot {
  return {
    type: 'column',
    data: {
      blocks: [
        { type: 'heading', data: { level: 'h3', text: title } },
        { type: 'text', data: { text: 'A concise description of this feature and why it matters.' } },
      ],
    },
  }
}

export const featureGridBasic: TemplateDefinition = {
  id: 'feature-grid-basic',
  name: 'Basic Feature Grid',
  description: 'Three columns of features with headings and short descriptions.',
  category: 'features',
  version: '1.0.0',
  // Real catalog thumbnail (PB-FREE-LIBRARY-DESIGN-1-F): a plugin-owned WebP rendered
  // from this template's canonical payload through the Runtime Preview engine.
  preview: '/vendor/page-builder/img/thumbnails/template-feature-grid-basic.webp',
  layout: {
    kind: 'tree',
    tree: {
      sections: [
        {
          type: 'section',
          // Canonical generic-section spacing (PB-FREE-LIBRARY-DESIGN-1-F): box-model
          // `spacing.padding` replaces the legacy `padding:{top,bottom}` object that threw
          // in section.blade. Vertical intent (48px) is preserved exactly.
          data: { spacing: { padding: { top: 48, right: 0, bottom: 48, left: 0 } } },
          blocks: [
            {
              type: 'container',
              data: {
                blocks: [featureColumn('Fast'), featureColumn('Reliable'), featureColumn('Secure')],
              },
            },
          ],
        },
      ],
    },
  },
}
