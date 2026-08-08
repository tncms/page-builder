// Phase 17 — Live patch map (pure). Single source of truth for translating an
// inspector field edit into a targeted DOM mutation. Imported by BOTH the shell
// (to build the granular patch messages) and the iframe patchers (whitelists).
//
// A patch NEVER changes structure: it only restyles, re-texts or re-attributes an
// element that already exists inside a component marker. Anything not mapped here
// is ignored (the full-render reconciliation on commit will pick it up instead).

export type PatchKind = 'style' | 'text' | 'attr'

export interface PatchTarget {
  kind: PatchKind
  /** CSS selector relative to the component marker; null = the rendered root element. */
  selector: string | null
  /** camelCase CSS property (style patches). */
  styleProp?: string
  /** attribute name (attr patches). */
  attr?: string
  /** value may be a design-token reference (e.g. "color.primary") to resolve. */
  token?: boolean
}

// CSS properties a style patch may set inline. Anything else is dropped by the
// stylePatcher so a bad map entry can never inject arbitrary CSS.
export const STYLE_WHITELIST = [
  'backgroundColor',
  'textAlign',
  'padding',
  'margin',
  'width',
  'color',
  // Phase 26.1.1 — column re-proportion hover preview (Layout popup). Columns
  // render their share via flex-basis, so patching width alone won't move them.
  'flexBasis',
  'maxWidth',
  // Phase 19 — per-side box-model spacing (Box Model overlay live patches).
  'paddingTop',
  'paddingRight',
  'paddingBottom',
  'paddingLeft',
  'marginTop',
  'marginRight',
  'marginBottom',
  'marginLeft',
] as const

export type StyleProp = (typeof STYLE_WHITELIST)[number]

export function isWhitelistedStyle(prop: string): prop is StyleProp {
  return (STYLE_WHITELIST as readonly string[]).includes(prop)
}

// Per component-type field maps for the built-in library. Field names match the
// section/block schema definitions; selectors/classes match the Blade views.
const MAP: Record<string, Record<string, PatchTarget>> = {
  cta: {
    title: { kind: 'text', selector: '.pb-cta__title' },
    text: { kind: 'text', selector: '.pb-cta__text' },
    button_label: { kind: 'text', selector: '.pb-cta__button' },
    button_url: { kind: 'attr', selector: '.pb-cta__button', attr: 'href' },
    align: { kind: 'style', selector: null, styleProp: 'textAlign' },
    background: { kind: 'style', selector: null, styleProp: 'backgroundColor', token: true },
  },
  'text-section': {
    content: { kind: 'text', selector: '.pb-text-section__content' },
    align: { kind: 'style', selector: null, styleProp: 'textAlign' },
  },
}

// Generic fallbacks that apply to any component root (common inline style fields).
const GENERIC: Record<string, PatchTarget> = {
  align: { kind: 'style', selector: null, styleProp: 'textAlign' },
  textAlign: { kind: 'style', selector: null, styleProp: 'textAlign' },
  background: { kind: 'style', selector: null, styleProp: 'backgroundColor', token: true },
  backgroundColor: { kind: 'style', selector: null, styleProp: 'backgroundColor', token: true },
}

/** Resolve the DOM target for a (component type, field), or null if not patchable. */
export function resolvePatchTarget(type: string, field: string): PatchTarget | null {
  return MAP[type]?.[field] ?? GENERIC[field] ?? null
}
