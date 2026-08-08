// Phase 26.3.18 — Builder UX guidance strings & intent helpers.
//
// A single, co-located source of truth for the human-facing guidance the Live
// Builder shows: panel descriptions, per-component tooltips, capability badges,
// the informational section-drop toast, and the "insert the element twin instead"
// smart suggestion. The Vue editor has no i18n runtime (all strings are hardcoded
// today), so centralizing them here keeps them consistent, testable, and trivial
// to translate later — without adding an architectural i18n layer this UX-only
// phase forbids.
//
// Pure data + pure functions. No Vue, no store, no side effects.

import type { DefinitionMeta } from '../../editor/types'

/** Short descriptions shown under each palette panel heading. */
export const PANEL_GUIDANCE = {
  sections: 'Create page layouts and major page structure. Sections are added at the page level.',
  elements: 'Add content inside Containers and Columns.',
  patterns: 'Ready-made layouts combining Sections and Elements.',
} as const

/** A component "capability" — an informational badge on each palette card. */
export type Capability = 'ROOT ONLY' | 'COLUMN READY' | 'LAYOUT' | 'CHILD LAYOUT'

/**
 * The capability badge for a component. Sections are page-level; layout blocks
 * (container/column) describe their nesting role; every other block is an element
 * that lives inside a column.
 */
export function capabilityOf(type: string, kind: 'section' | 'block'): Capability {
  if (kind === 'section') return 'ROOT ONLY'
  if (type === 'container') return 'LAYOUT'
  if (type === 'column') return 'CHILD LAYOUT'
  return 'COLUMN READY'
}

/** The human "kind" word for a card (paired with an icon, never icon-only). */
export function kindLabel(kind: 'section' | 'block'): string {
  return kind === 'section' ? 'Section' : 'Element'
}

// Per-type tooltip copy. Falls back to a generated sentence for any component not
// explicitly listed, so a new registration is never left tooltip-less.
const TOOLTIPS: Record<string, string> = {
  // Sections
  section: 'Creates a new page section — a top-level building block. Added at page level.',
  hero: 'Creates a complete hero section. Added at page level.',
  'text-section': 'Creates a new page section for a block of text. Added at page level.',
  'image-section': 'Creates a new page section containing an image. Can only be added at page level.',
  columns: 'Creates a multi-column page section. Added at page level.',
  cta: 'Creates a call-to-action section. Added at page level.',
  faq: 'Creates a frequently-asked-questions section. Added at page level.',
  testimonial: 'Creates a testimonial section. Added at page level.',
  'logo-strip': 'Creates a strip of logos. Added at page level.',
  // Layout blocks
  container: 'Adds a layout Container to hold Columns inside the selected Section.',
  column: 'Adds a Column inside the selected Container.',
  // Content elements
  text: 'Adds a text element inside the selected Container or Column.',
  heading: 'Adds a heading inside the selected Container or Column.',
  image: 'Adds an image inside the selected Container or Column.',
  button: 'Adds a button inside the selected layout.',
  icon: 'Adds an icon inside the selected Container or Column.',
  quote: 'Adds a quote inside the selected Container or Column.',
  logo: 'Adds a logo image inside the selected Container or Column.',
  'accordion-item': 'Adds an accordion item inside the selected Container or Column.',
}

/** Tooltip text for a component card (explicit copy, or a safe generated fallback). */
export function tooltipFor(def: DefinitionMeta, kind: 'section' | 'block'): string {
  const explicit = TOOLTIPS[def.type]
  if (explicit) return explicit
  const label = (def.label as string) || (def.name as string) || def.type
  return kind === 'section'
    ? `Creates a new "${label}" section. Added at page level.`
    : `Adds "${label}" inside the selected Container or Column.`
}

// Section → element-twin mapping for the smart suggestion. When a user reaches for
// a Section whose intent is really a single element (an Image Section vs an Image),
// we can offer to insert the element twin into the selected layout instead.
const ELEMENT_TWIN: Record<string, string> = {
  'image-section': 'image',
  'text-section': 'text',
}

/** The block type that is the element-equivalent of a section, if one exists. */
export function elementTwinType(sectionType: string): string | null {
  return ELEMENT_TWIN[sectionType] ?? null
}

/** Copy for the "insert the element twin instead" affordance. */
export function insertTwinLabel(twinLabel: string): string {
  return `Insert ${twinLabel} element instead`
}

/** Helper shown when Element cards are disabled (no valid host selected). */
export const ELEMENT_CONTEXT_HELP = 'Select a Container or Column before adding an Element.'

/** Non-blocking toast shown after a Section snaps to page level from inside content. */
export const SECTION_AT_PAGE_LEVEL_TOAST =
  'Sections can only be placed at page level. Use Image, Heading, Text or other Elements inside Columns.'
