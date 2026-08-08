// Phase 18 — Inline text locator (pure). Single source of truth mapping a
// component type to its inline-EDITABLE text fields and the DOM selector that
// renders each. Used by the iframe (to detect what a double-click targets) and
// the shell (to know multiline/translatable). A field absent here simply isn't
// inline-editable — it stays fully editable via the Property Inspector.
//
// Selectors/classes are verified against the built-in Blade views; field names
// against the section/block schema definitions.

export interface InlineField {
  field: string
  selector: string
  multiline: boolean
  translatable: boolean
}

const MAP: Record<string, InlineField[]> = {
  // ---- Sections ----
  hero: [
    { field: 'title', selector: '.pb-hero__title', multiline: false, translatable: true },
    { field: 'subtitle', selector: '.pb-hero__subtitle', multiline: true, translatable: true },
    { field: 'button_label', selector: '.pb-hero__button', multiline: false, translatable: true },
  ],
  cta: [
    { field: 'title', selector: '.pb-cta__title', multiline: false, translatable: true },
    { field: 'text', selector: '.pb-cta__text', multiline: true, translatable: true },
    { field: 'button_label', selector: '.pb-cta__button', multiline: false, translatable: true },
  ],
  'text-section': [
    { field: 'content', selector: '.pb-text-section__content', multiline: true, translatable: true },
  ],
  // ---- Blocks ----
  heading: [{ field: 'text', selector: '.pb-block--heading', multiline: false, translatable: true }],
  text: [{ field: 'text', selector: '.pb-block--text', multiline: true, translatable: true }],
  button: [{ field: 'label', selector: '.pb-button', multiline: false, translatable: true }],
  quote: [
    { field: 'text', selector: '.pb-quote__text', multiline: true, translatable: true },
    { field: 'author', selector: '.pb-quote__author', multiline: false, translatable: true },
  ],
  'accordion-item': [
    { field: 'question', selector: '.pb-accordion-item__question', multiline: false, translatable: true },
    { field: 'answer', selector: '.pb-accordion-item__answer', multiline: true, translatable: true },
  ],
}

/** The inline-editable fields for a component type (empty when none). */
export function inlineFieldsFor(type: string): InlineField[] {
  return MAP[type] ?? []
}

export function isInlineEditable(type: string): boolean {
  return inlineFieldsFor(type).length > 0
}

/**
 * Given a component marker element + type and a clicked target, return the
 * inline field + its element whose selector contains the target, or null.
 */
export function locateField(
  type: string,
  markerEl: Element,
  target: Element,
): { field: InlineField; el: HTMLElement } | null {
  for (const field of inlineFieldsFor(type)) {
    const el = markerEl.querySelector(field.selector)
    if (el instanceof HTMLElement && (el === target || el.contains(target))) {
      return { field, el }
    }
  }
  return null
}

/** Resolve the DOM element for a (marker, field selector). */
export function elementFor(markerEl: Element, selector: string): HTMLElement | null {
  const el = markerEl.querySelector(selector)
  return el instanceof HTMLElement ? el : null
}
