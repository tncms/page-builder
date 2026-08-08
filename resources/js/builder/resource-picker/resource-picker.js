/**
 * Builder Resource Picker bridge (Phase 26.47B).
 *
 * A thin host-side layer that lets the Builder use the Design Library as a
 * context-aware resource picker. It opens the (unchanged) Design Library in
 * Picker mode inside an overlay iframe and relays the user's selection back to
 * the Builder:
 *
 *   - Full page  → the Design Library imports it via FullPageImportService and
 *                  posts `page-builder:template-imported`; the host reloads the
 *                  Builder so it re-reads the freshly-saved draft.
 *   - Section/Block → the Design Library posts `page-builder:insert-resource`
 *                  ({ rid, kind }); the host re-dispatches it as a decoupled
 *                  intent event on `document`. The Builder OWNS the insertion —
 *                  this bridge never mutates Builder state, serialization, or the
 *                  CommandBus.
 *
 * The bridge is inert until opened. It exposes `window.TNPageBuilderResourcePicker`
 * so the Builder's own "Insert Section" / "Insert Block" affordances can open the
 * correctly-scoped picker.
 */
const CONFIG_SELECTOR = '[data-pb-resource-picker]';
const STYLE_ID = 'pb-resource-picker-style';

/**
 * @typedef {{ urls: { page: string, section: string, block: string }, insertEvent: string, importedEvent: string }} PickerConfig
 * @returns {PickerConfig | null}
 */
function readConfig() {
  const el = document.querySelector(CONFIG_SELECTOR);
  if (!el) return null;
  return {
    urls: {
      page: el.getAttribute('data-page-url') || '',
      section: el.getAttribute('data-section-url') || '',
      block: el.getAttribute('data-block-url') || '',
    },
    insertEvent: el.getAttribute('data-insert-event') || 'page-builder:insert-resource',
    importedEvent: el.getAttribute('data-imported-event') || 'page-builder:template-imported',
  };
}

function injectStyles() {
  if (document.getElementById(STYLE_ID)) return;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
    .pb-picker-overlay[hidden] { display: none; }
    .pb-picker-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; flex-direction: column;
      background: rgba(6,8,12,.86); }
    .pb-picker-overlay__bar { display: flex; align-items: center; justify-content: space-between;
      gap: 1rem; padding: .6rem 1rem; background: #171a21; color: #e7eaf0; border-bottom: 1px solid #262b36; }
    .pb-picker-overlay__close { background: transparent; color: inherit; border: 1px solid #262b36;
      border-radius: 8px; padding: .35rem .7rem; cursor: pointer; font: inherit; }
    .pb-picker-overlay__frame { flex: 1; width: 100%; border: 0; background: #0f1115; }
  `;
  document.head.appendChild(style);
}

function createOverlay() {
  injectStyles();
  const overlay = document.createElement('div');
  overlay.className = 'pb-picker-overlay';
  overlay.hidden = true;
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-label', 'Design Library picker');
  overlay.innerHTML =
    '<div class="pb-picker-overlay__bar">' +
    '<strong class="pb-picker-overlay__title">Design Library</strong>' +
    '<button type="button" class="pb-picker-overlay__close" data-pb-picker-close aria-label="Close picker">✕</button>' +
    '</div>' +
    '<iframe class="pb-picker-overlay__frame" title="Design Library picker" data-pb-picker-frame></iframe>';
  document.body.appendChild(overlay);
  return overlay;
}

function boot() {
  const config = readConfig();
  if (!config) return;

  const overlay = createOverlay();
  const frame = overlay.querySelector('[data-pb-picker-frame]');
  const closeBtn = overlay.querySelector('[data-pb-picker-close]');
  let lastFocus = null;

  const open = (kind) => {
    const url = config.urls[kind];
    if (!url || !frame) return false;
    lastFocus = document.activeElement;
    frame.src = url;
    overlay.hidden = false;
    if (closeBtn && closeBtn.focus) closeBtn.focus();
    return true;
  };

  const close = () => {
    overlay.hidden = true;
    if (frame) frame.removeAttribute('src');
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  };

  // The Design Library posts selection/import events from inside the iframe. Only
  // accept same-origin messages, then hand the operation to the Builder.
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin) return;
    const data = event.data || {};
    if (data.type === config.insertEvent) {
      // Builder owns the mutation: forward the selection as a decoupled intent.
      document.dispatchEvent(new CustomEvent(config.insertEvent, { detail: { rid: data.rid, kind: data.kind } }));
      close();
    } else if (data.type === config.importedEvent) {
      close();
      // Reuse existing Builder loading — reload to re-read the freshly-saved draft.
      window.location.reload();
    }
  });

  document.addEventListener('click', (event) => {
    const importBtn = event.target.closest('[data-pb-import-design]');
    if (importBtn) {
      event.preventDefault();
      open('page');
      return;
    }
    if (!overlay.hidden && (event.target === overlay || event.target.closest('[data-pb-picker-close]'))) {
      close();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !overlay.hidden) close();
  });

  // Expose the picker so the Builder's own Insert Section / Insert Block controls
  // can open the correctly-scoped picker without touching this bridge's internals.
  window.TNPageBuilderResourcePicker = {
    open,
    close,
    contexts: () => ({ ...config.urls }),
  };
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
