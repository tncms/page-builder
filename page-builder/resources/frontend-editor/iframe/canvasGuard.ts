// LT-M1 Increment 2D — editor-canvas navigation guard (Frontend editor iframe).
//
// The frontend editor iframe embeds the owner's REAL public document, so the
// theme's own header / menu / logo / language controls (rendered OUTSIDE the
// `#pb-frontend-canvas` region) and any in-canvas CTA links would navigate the
// iframe away — or switch locale — and desynchronise or unload the editor.
// Inside Page Builder the rendered website is an Editor Canvas, not an
// interactive website.
//
// This guard neutralises navigation-class actions at the iframe DOCUMENT
// boundary: anchor clicks (menu / logo / language / CTA), middle/modified-click
// link opens, and form submissions. It ONLY cancels the default browser action;
// it NEVER stops propagation, so the Page Builder marker handlers (selection,
// hover, context menu, drag, inline edit) still receive every event and keep
// working. Public visitors never load this script (see
// FrontendEditorRenderer::active), so the public frontend is unaffected.
//
// LT-M1 closure — it also marks the document (`html.pb-fe-noninteractive`) so the
// scoped iframe.css renders those navigation controls with a default cursor: a
// visual signal that the canvas is preview-only. Page Builder overlays/handles
// live in the shell document and inline-edit affordances keep their own cursor,
// so both are untouched.

/** Editor-only marker class: scoped iframe styles read the canvas as preview. */
export const CANVAS_NONINTERACTIVE_CLASS = 'pb-fe-noninteractive'

/**
 * Install the navigation guard on an iframe document. Returns a disposer that
 * removes every listener + the marker class again (used by tests; the editor
 * iframe lives for the whole session).
 */
export function installCanvasNavigationGuard(doc: Document): () => void {
  const guardAnchor = (event: MouseEvent): void => {
    // A Page Builder handler (e.g. inline-edit dblclick) may have already claimed
    // this event — don't second-guess it.
    if (event.defaultPrevented) {
      return
    }

    const target = event.target instanceof Element ? event.target : null
    // Menu / logo / language / CTA controls are all anchors. Cancel the
    // navigation only — propagation continues so an in-canvas link still selects
    // its Page Builder component.
    if (target?.closest('a[href]')) {
      event.preventDefault()
    }
  }

  const guardSubmit = (event: Event): void => {
    // Search / newsletter / language <form> posts must not leave the editor.
    event.preventDefault()
  }

  doc.addEventListener('click', guardAnchor, true)
  doc.addEventListener('auxclick', guardAnchor, true)
  doc.addEventListener('submit', guardSubmit, true)
  doc.documentElement.classList.add(CANVAS_NONINTERACTIVE_CLASS)

  return () => {
    doc.removeEventListener('click', guardAnchor, true)
    doc.removeEventListener('auxclick', guardAnchor, true)
    doc.removeEventListener('submit', guardSubmit, true)
    doc.documentElement.classList.remove(CANVAS_NONINTERACTIVE_CLASS)
  }
}
