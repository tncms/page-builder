/**
 * Design Library — progressive enhancement (Phase 26.43).
 *
 * The page is fully functional without this script (server-rendered cards; filters
 * submit via query parameters). This module adds:
 *   - local favorites (localStorage only — no accounts, no sync);
 *   - a preview modal that loads the Runtime Preview route with device switching;
 *   - an insert-intent event that the existing editor insert flow can consume.
 *
 * It never renders resource HTML itself and never touches Builder serialization,
 * the CommandBus, or any editor state — previews come from the Preview route and
 * insertion is a decoupled intent event.
 */
const FAVORITES_KEY = 'pb.design-library.favorites';
const INSERT_EVENT = 'page-builder:insert-resource';

const readFavorites = () => {
  try {
    const raw = localStorage.getItem(FAVORITES_KEY);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? new Set(list.filter((v) => typeof v === 'string')) : new Set();
  } catch (_) {
    return new Set();
  }
};

const writeFavorites = (set) => {
  try {
    localStorage.setItem(FAVORITES_KEY, JSON.stringify([...set].sort()));
  } catch (_) {
    /* storage unavailable — favorites simply won't persist */
  }
};

const withDevice = (url, device) => {
  try {
    const u = new URL(url, window.location.origin);
    u.searchParams.set('device', device);
    return u.toString();
  } catch (_) {
    return url;
  }
};

function initFavorites(root) {
  const favorites = readFavorites();

  const applyMarks = () => {
    root.querySelectorAll('[data-design-library-card]').forEach((card) => {
      const rid = card.getAttribute('data-rid');
      const btn = card.querySelector('[data-design-library-favorite]');
      if (btn) btn.setAttribute('aria-pressed', favorites.has(rid) ? 'true' : 'false');
    });
  };

  root.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-design-library-favorite]');
    if (!btn) return;
    const card = btn.closest('[data-design-library-card]');
    const rid = card && card.getAttribute('data-rid');
    if (!rid) return;
    if (favorites.has(rid)) favorites.delete(rid);
    else favorites.add(rid);
    writeFavorites(favorites);
    applyMarks();
    applyFavoritesFilter(root, favorites);
  });

  applyMarks();

  const checkbox = root.querySelector('input[name="favorites"]');
  if (checkbox) {
    checkbox.addEventListener('change', (event) => {
      event.stopPropagation();
      applyFavoritesFilter(root, favorites, checkbox.checked);
    });
    if (checkbox.checked) applyFavoritesFilter(root, favorites, true);
  }
}

function applyFavoritesFilter(root, favorites, active) {
  const checkbox = root.querySelector('input[name="favorites"]');
  const on = active !== undefined ? active : !!(checkbox && checkbox.checked);
  root.querySelectorAll('[data-design-library-card]').forEach((card) => {
    const rid = card.getAttribute('data-rid');
    card.hidden = on && !favorites.has(rid);
  });
}

function initInsert(root, importer) {
  root.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-design-library-insert]');
    if (!btn) return;
    const card = btn.closest('[data-design-library-card]');
    const rid = card && card.getAttribute('data-rid');
    if (!rid) return;
    // Entitlement is enforced server-side; the UI simply never initiates an insert
    // or import for a locked resource (its button carries no insert hook anyway).
    if (card.getAttribute('data-locked') === '1') return;
    const kind = card.getAttribute('data-kind');

    // Full Page Templates use the import workflow when a target document is set;
    // everything else emits the decoupled insert intent for the existing editor
    // flow. No command dispatch, no serialization change either way.
    if (kind === 'page' && importer && importer.canImport()) {
      importer.open(card);
      return;
    }

    const detail = { rid, kind };
    document.dispatchEvent(new CustomEvent(INSERT_EVENT, { detail, bubbles: true }));
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: INSERT_EVENT, ...detail }, window.location.origin);
    }
  });
}

function initImport(root) {
  const modal = document.querySelector('[data-design-library-import]');
  const ownerType = root.getAttribute('data-owner-type');
  const ownerId = root.getAttribute('data-owner-id');
  const planUrl = root.getAttribute('data-import-plan-url');
  const commitUrl = root.getAttribute('data-import-url');
  const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const canImport = () => !!(modal && ownerType && ownerId && planUrl && commitUrl);

  if (!modal) return { canImport: () => false, open() {} };

  const summary = modal.querySelector('[data-import-summary]');
  const warnings = modal.querySelector('[data-import-warnings]');
  const blockers = modal.querySelector('[data-import-blockers]');
  const confirmBtn = modal.querySelector('[data-design-library-import-confirm]');
  let current = null;
  let lastFocus = null;

  const post = (url, body) =>
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then((r) => r.json().then((data) => ({ status: r.status, data })));

  const renderIssues = (el, issues) => {
    if (!el) return;
    if (!issues || !issues.length) {
      el.style.display = 'none';
      el.textContent = '';
      return;
    }
    el.style.display = 'block';
    el.textContent = issues.map((i) => i.message).join(' ');
  };

  const close = () => {
    modal.hidden = true;
    current = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };

  const open = (card) => {
    lastFocus = document.activeElement;
    current = null;
    modal.hidden = false;
    if (summary) summary.textContent = 'Preparing preview…';
    renderIssues(warnings, []);
    renderIssues(blockers, []);
    if (confirmBtn) confirmBtn.disabled = true;
    const closeBtn = modal.querySelector('[data-design-library-import-close]');
    if (closeBtn) closeBtn.focus();

    post(planUrl, { rid: card.getAttribute('data-rid'), ownerType, ownerId })
      .then(({ data }) => {
        const plan = (data && data.data) || {};
        current = plan;
        const c = plan.counts || {};
        if (summary) {
          summary.textContent = plan.ok
            ? `Replace ${c.currentSections || 0} sections / ${c.currentBlocks || 0} blocks with ${c.incomingSections || 0} sections / ${c.incomingBlocks || 0} blocks. This replaces the current draft only; the published page is unchanged until you publish.`
            : 'This design cannot be imported.';
        }
        renderIssues(warnings, plan.warnings);
        renderIssues(blockers, plan.blockers);
        if (confirmBtn) confirmBtn.disabled = !plan.ok;
      })
      .catch(() => {
        if (summary) summary.textContent = 'Could not prepare the import.';
      });
  };

  modal.addEventListener('click', (event) => {
    if (event.target === modal || event.target.closest('[data-design-library-import-close]')) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
    if (event.key === 'Tab' && !modal.hidden) trapFocus(event, modal);
  });

  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      if (!current || !current.ok) return;
      confirmBtn.disabled = true;
      if (summary) summary.textContent = 'Importing…';
      post(commitUrl, { rid: current.rid, ownerType, ownerId, planHash: current.planHash, revision: current.revision })
        .then(({ data }) => {
          const result = (data && data.data) || {};
          if (result.replaced) {
            const detail = { rid: result.rid, ownerType, ownerId };
            document.dispatchEvent(new CustomEvent('page-builder:template-imported', { detail }));
            // When embedded as a Builder picker (iframe), notify the host so it can
            // close the picker and reload the Builder from the freshly-saved draft.
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({ type: 'page-builder:template-imported', ...detail }, window.location.origin);
            }
            close();
          } else {
            renderIssues(blockers, result.issues);
            if (summary) summary.textContent = 'Import did not complete; the draft is unchanged.';
          }
        })
        .catch(() => {
          if (summary) summary.textContent = 'Import failed; the draft is unchanged.';
        });
    });
  }

  return { canImport, open };
}

function initModal(root) {
  const modal = root.querySelector('[data-design-library-modal]');
  if (!modal) return;
  const frame = modal.querySelector('[data-design-library-frame]');
  const title = modal.querySelector('#dl-modal-title');
  const deviceButtons = [...modal.querySelectorAll('[data-device]')];
  let baseUrl = '';
  let device = 'desktop';
  let lastFocus = null;

  const load = () => {
    if (frame && baseUrl) frame.src = withDevice(baseUrl, device);
  };

  const setDevice = (next) => {
    device = next;
    deviceButtons.forEach((b) => b.setAttribute('aria-pressed', b.getAttribute('data-device') === next ? 'true' : 'false'));
    load();
  };

  const open = (card) => {
    baseUrl = card.getAttribute('data-preview-url') || '';
    if (title) title.textContent = 'Preview — ' + (card.getAttribute('data-name') || '');
    lastFocus = document.activeElement;
    modal.hidden = false;
    setDevice('desktop');
    const closeBtn = modal.querySelector('[data-design-library-close]');
    if (closeBtn) closeBtn.focus();
  };

  const close = () => {
    modal.hidden = true;
    if (frame) frame.removeAttribute('src');
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  };

  root.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-design-library-preview]');
    if (trigger) {
      const card = trigger.closest('[data-design-library-card]');
      if (card) open(card);
    }
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal || event.target.closest('[data-design-library-close]')) close();
    const deviceBtn = event.target.closest('[data-device]');
    if (deviceBtn) setDevice(deviceBtn.getAttribute('data-device'));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
    if (event.key === 'Tab' && !modal.hidden) trapFocus(event, modal);
  });
}

function trapFocus(event, container) {
  const focusable = container.querySelectorAll('button, iframe, [href], input, [tabindex]:not([tabindex="-1"])');
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    last.focus();
    event.preventDefault();
  } else if (!event.shiftKey && document.activeElement === last) {
    first.focus();
    event.preventDefault();
  }
}

function boot() {
  const root = document.querySelector('main.dl-shell') || document.body;
  initFavorites(root);
  const importer = initImport(root);
  initInsert(root, importer);
  initModal(document);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
