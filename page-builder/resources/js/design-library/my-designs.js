/**
 * My Designs management module (Phase 26.49).
 *
 * Optional progressive enhancement for the My Designs surface. It wires the card
 * actions (edit metadata, open in Builder, duplicate, archive, restore, delete)
 * to their JSON endpoints. It NEVER touches the payload, fingerprint or RID — the
 * metadata dialog only carries descriptive fields, and "Open in Builder" hands off
 * an isolated working copy via a decoupled CustomEvent. All mutations are verified
 * server-side; this layer is convenience only (the page also works without it).
 *
 * The source file IS the served asset (no build step).
 */
(() => {
  const shell = document.querySelector('.dl-shell');
  if (!shell) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const endpoints = {
    metadata: shell.dataset.endpointMetadata || '',
    archive: shell.dataset.endpointArchive || '',
    restore: shell.dataset.endpointRestore || '',
    delete: shell.dataset.endpointDelete || '',
    duplicate: shell.dataset.endpointDuplicate || '',
    open: shell.dataset.endpointOpen || '',
  };

  const toast = document.querySelector('[data-my-designs-toast]');
  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(() => { toast.hidden = true; }, 2600);
  };

  const post = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    let data = null;
    try { data = await res.json(); } catch { /* ignore non-JSON */ }
    return { ok: res.ok, status: res.status, data };
  };

  // ---- Metadata dialog ------------------------------------------------------
  const mdDialog = document.querySelector('[data-metadata-dialog]');
  const mdForm = document.querySelector('[data-metadata-form]');
  const mdError = document.querySelector('[data-metadata-error]');
  let lastFocus = null;

  const setField = (name, value) => {
    const el = mdForm?.querySelector(`[data-field="${name}"]`);
    if (el) el.value = value ?? '';
  };

  const openMetadata = (card) => {
    if (!mdDialog || !mdForm) return;
    lastFocus = document.activeElement;
    setField('rid', card.dataset.rid);
    setField('name', card.dataset.name);
    setField('description', card.dataset.description);
    setField('category', card.dataset.category);
    setField('tags', card.dataset.tags);
    setField('visibility', card.dataset.visibility || 'private');
    setField('versionNotes', card.dataset.versionNotes);
    if (mdError) { mdError.style.display = 'none'; mdError.textContent = ''; }
    mdDialog.hidden = false;
    mdForm.querySelector('[data-field="name"]')?.focus();
  };

  const closeMetadata = () => {
    if (mdDialog) mdDialog.hidden = true;
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  };

  mdDialog?.querySelectorAll('[data-metadata-close]').forEach((btn) => btn.addEventListener('click', closeMetadata));

  mdForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(mdForm);
    const payload = {
      rid: fd.get('rid'),
      name: fd.get('name'),
      description: fd.get('description'),
      category: fd.get('category'),
      tags: String(fd.get('tags') || '').split(',').map((t) => t.trim()).filter(Boolean),
      visibility: fd.get('visibility'),
      versionNotes: fd.get('versionNotes'),
    };
    const { ok, data } = await post(endpoints.metadata, payload);
    if (ok) {
      showToast('Metadata saved.');
      closeMetadata();
      window.location.reload();
      return;
    }
    if (mdError) {
      const issues = (data && data.issues) || [];
      mdError.textContent = issues.length ? issues.map((i) => i.message).join(' ') : 'Could not save metadata.';
      mdError.style.display = 'block';
    }
  });

  // ---- Archive / delete confirm dialog --------------------------------------
  const adDialog = document.querySelector('[data-archive-dialog]');
  const adTitle = document.querySelector('[data-archive-title]');
  const adMessage = document.querySelector('[data-archive-message]');
  const adConfirm = document.querySelector('[data-archive-confirm]');
  let pending = null;

  const confirmCopy = {
    archive: { title: 'Archive design', message: 'This design will be hidden from normal browsing. You can restore it any time.' },
    delete: { title: 'Delete design', message: 'This soft-deletes the design. It is removed from your list but never permanently erased.' },
  };

  const openConfirm = (action, rid) => {
    if (!adDialog) return;
    pending = { action, rid };
    const copy = confirmCopy[action];
    if (adTitle) adTitle.textContent = copy.title;
    if (adMessage) adMessage.textContent = copy.message;
    adDialog.hidden = false;
    adConfirm?.focus();
  };

  const closeConfirm = () => { if (adDialog) adDialog.hidden = true; pending = null; };
  adDialog?.querySelectorAll('[data-archive-close]').forEach((btn) => btn.addEventListener('click', closeConfirm));

  adConfirm?.addEventListener('click', async () => {
    if (!pending) return;
    const { action, rid } = pending;
    const { ok } = await post(endpoints[action], { rid });
    closeConfirm();
    if (ok) { showToast(action === 'delete' ? 'Design deleted.' : 'Design archived.'); window.location.reload(); }
    else showToast('Action not permitted.');
  });

  // ---- Card actions ---------------------------------------------------------
  document.querySelector('[data-my-designs-grid]')?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const card = btn.closest('.dl-card');
    if (!card) return;
    const rid = card.dataset.rid;
    const action = btn.dataset.action;

    if (action === 'edit') { openMetadata(card); return; }
    if (action === 'archive' || action === 'delete') { openConfirm(action, rid); return; }

    if (action === 'restore') {
      const { ok } = await post(endpoints.restore, { rid });
      if (ok) { showToast('Design restored.'); window.location.reload(); } else showToast('Action not permitted.');
      return;
    }

    if (action === 'duplicate') {
      const { ok, data } = await post(endpoints.duplicate, { rid });
      if (ok) { showToast('Design duplicated.'); window.location.reload(); } else showToast('Action not permitted.');
      return;
    }

    if (action === 'open') {
      const { ok, data } = await post(endpoints.open, { rid });
      if (ok && data && data.data) {
        // Decoupled hand-off: the Builder consumes this isolated working copy as a
        // fresh draft. Editing it and choosing "Save as Design" creates a NEW
        // design — the repository resource is never edited in place.
        window.dispatchEvent(new CustomEvent('page-builder:open-working-copy', { detail: data.data }));
        showToast('Opening a working copy in the Builder…');
      } else showToast('Action not permitted.');
    }
  });

  // Escape closes whichever dialog is open.
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (mdDialog && !mdDialog.hidden) closeMetadata();
    if (adDialog && !adDialog.hidden) closeConfirm();
  });
})();
