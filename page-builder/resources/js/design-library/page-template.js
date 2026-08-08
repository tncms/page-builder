/**
 * Page Template destination workflow (Phase 26.47D — Part A).
 *
 * Admin "Use This Template": pick where to apply a full-page template, then apply
 * it through the server. Two destinations:
 *   - Create New Page/Post — POST create-apply → redirect to the Builder editor.
 *   - Use Existing Page/Post — search a target, review the current/incoming counts
 *     (via the existing import plan endpoint), confirm the destructive replace,
 *     then POST apply-existing → redirect to the Builder editor.
 *
 * This module is progressive enhancement only. It never renders resource HTML,
 * never touches Builder state/serialization, and performs no application itself —
 * the server applies through the unchanged FullPageImportService. It is admin-only
 * (Picker mode never loads it).
 */
const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

const postJson = (url, body) =>
  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  }).then((r) => r.json().then((data) => ({ status: r.status, data })).catch(() => ({ status: r.status, data: {} })));

const getJson = (url) =>
  fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then((r) => r.json().catch(() => ({})));

function parseChoice(value) {
  const [mode, ownerType] = String(value || '').split(':');
  return { mode: mode === 'existing' ? 'existing' : 'create', ownerType: ownerType === 'post' ? 'post' : 'page' };
}

function config(shell) {
  return {
    destinations: shell.getAttribute('data-template-destinations-url') || '',
    create: shell.getAttribute('data-template-create-url') || '',
    applyExisting: shell.getAttribute('data-template-apply-existing-url') || '',
    plan: shell.getAttribute('data-import-plan-url') || '',
  };
}

function initDestinations(shell) {
  const modal = document.querySelector('[data-design-library-destination]');
  if (!modal) return;
  const urls = config(shell);

  const els = {
    name: modal.querySelector('[data-dest-template-name]'),
    createPanel: modal.querySelector('[data-dest-create]'),
    existingPanel: modal.querySelector('[data-dest-existing]'),
    title: modal.querySelector('[data-dest-title]'),
    search: modal.querySelector('[data-dest-search]'),
    results: modal.querySelector('[data-dest-results]'),
    summary: modal.querySelector('[data-dest-summary]'),
    confirmRow: modal.querySelector('[data-dest-confirm-row]'),
    confirmCheck: modal.querySelector('[data-dest-confirm-check]'),
    error: modal.querySelector('[data-dest-error]'),
    confirm: modal.querySelector('[data-design-library-destination-confirm]'),
  };

  const state = { rid: null, mode: 'create', ownerType: 'page', target: null, plan: null };
  let lastFocus = null;
  let searchTimer = null;

  const choice = () => parseChoice((modal.querySelector('input[name="dl-dest"]:checked') || {}).value);

  const showError = (message) => {
    if (!els.error) return;
    els.error.style.display = message ? 'block' : 'none';
    els.error.textContent = message || '';
  };

  const refreshConfirmState = () => {
    if (state.mode === 'create') {
      els.confirm.disabled = false;
      return;
    }
    // Existing: require a chosen target that produced an OK plan AND the destructive confirm.
    const ready = !!(state.target && state.plan && state.plan.ok && els.confirmCheck && els.confirmCheck.checked);
    els.confirm.disabled = !ready;
  };

  const renderPlan = () => {
    if (!els.summary) return;
    if (!state.target) {
      els.summary.textContent = '';
      if (els.confirmRow) els.confirmRow.hidden = true;
      return;
    }
    const plan = state.plan;
    if (!plan) {
      els.summary.textContent = 'Preparing preview…';
      return;
    }
    const c = plan.counts || {};
    els.summary.textContent = plan.ok
      ? `Replace ${c.currentSections || 0} sections / ${c.currentBlocks || 0} blocks with ${c.incomingSections || 0} sections / ${c.incomingBlocks || 0} blocks. This replaces the current draft only; the published page is unchanged until you publish.`
      : ((plan.blockers || []).map((i) => i.message).join(' ') || 'This template cannot be applied to that destination.');
    if (els.confirmRow) els.confirmRow.hidden = !plan.ok;
    refreshConfirmState();
  };

  const loadPlan = () => {
    state.plan = null;
    renderPlan();
    if (!state.target || !urls.plan) return;
    postJson(urls.plan, { rid: state.rid, ownerType: state.ownerType, ownerId: state.target })
      .then(({ data }) => {
        state.plan = (data && data.data) || {};
        renderPlan();
      })
      .catch(() => {
        if (els.summary) els.summary.textContent = 'Could not prepare the replacement preview.';
      });
  };

  const renderResults = (items) => {
    if (!els.results) return;
    els.results.innerHTML = '';
    state.target = null;
    renderPlan();
    (items || []).forEach((item) => {
      const li = document.createElement('li');
      const label = document.createElement('label');
      label.style.cssText = 'display:flex; gap:.5rem; align-items:center; padding:.35rem .5rem; border:1px solid var(--dl-line); border-radius:8px; cursor:pointer;';
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = 'dl-dest-target';
      radio.value = String(item.id);
      radio.addEventListener('change', () => {
        state.target = item.id;
        loadPlan();
      });
      const text = document.createElement('span');
      text.textContent = `${item.title} (#${item.id}${item.status ? ' · ' + item.status : ''})`;
      label.append(radio, text);
      li.append(label);
      els.results.append(li);
    });
  };

  const searchDestinations = () => {
    if (!urls.destinations || !els.results) return;
    const q = (els.search && els.search.value.trim()) || '';
    const url = `${urls.destinations}?ownerType=${encodeURIComponent(state.ownerType)}&q=${encodeURIComponent(q)}`;
    getJson(url)
      .then((res) => renderResults((res && res.data) || []))
      .catch(() => renderResults([]));
  };

  const applyMode = () => {
    const { mode, ownerType } = choice();
    state.mode = mode;
    state.ownerType = ownerType;
    state.target = null;
    state.plan = null;
    showError('');
    if (els.createPanel) els.createPanel.hidden = mode !== 'create';
    if (els.existingPanel) els.existingPanel.hidden = mode !== 'existing';
    if (mode === 'existing') searchDestinations();
    renderPlan();
    refreshConfirmState();
  };

  const open = (card) => {
    state.rid = card.getAttribute('data-rid');
    state.plan = null;
    state.target = null;
    if (els.name) els.name.textContent = `Template: ${card.getAttribute('data-name') || state.rid}`;
    if (els.title) els.title.value = '';
    if (els.search) els.search.value = '';
    if (els.confirmCheck) els.confirmCheck.checked = false;
    const firstRadio = modal.querySelector('input[name="dl-dest"]');
    if (firstRadio) firstRadio.checked = true;
    lastFocus = document.activeElement;
    modal.hidden = false;
    applyMode();
    const closeBtn = modal.querySelector('[data-design-library-destination-close]');
    if (closeBtn) closeBtn.focus();
  };

  const close = () => {
    modal.hidden = true;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };

  const redirect = (data) => {
    const to = data && data.data && data.data.redirect;
    if (to) {
      window.location.assign(to);
    } else {
      close();
    }
  };

  const submit = () => {
    showError('');
    els.confirm.disabled = true;
    if (state.mode === 'create') {
      postJson(urls.create, { rid: state.rid, ownerType: state.ownerType, title: (els.title && els.title.value) || '' })
        .then(({ status, data }) => {
          if (status === 200 && data && data.success) return redirect(data);
          const issues = (data && data.data && data.data.issues) || [];
          showError(issues.map((i) => i.message).join(' ') || 'Could not create and apply the template.');
          refreshConfirmState();
        })
        .catch(() => {
          showError('Could not create and apply the template.');
          refreshConfirmState();
        });
      return;
    }

    const plan = state.plan || {};
    postJson(urls.applyExisting, {
      rid: state.rid,
      ownerType: state.ownerType,
      ownerId: state.target,
      planHash: plan.planHash,
      revision: plan.revision,
    })
      .then(({ status, data }) => {
        if (status === 200 && data && data.success) return redirect(data);
        const issues = (data && data.data && data.data.issues) || [];
        showError(issues.map((i) => i.message).join(' ') || 'Could not apply the template; the draft is unchanged.');
        // A stale plan/revision means our counts are outdated — re-plan.
        loadPlan();
      })
      .catch(() => {
        showError('Could not apply the template; the draft is unchanged.');
        refreshConfirmState();
      });
  };

  // Card → open dialog.
  shell.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-design-library-use-template]');
    if (!btn) return;
    const card = btn.closest('[data-design-library-card]');
    if (card) open(card);
  });

  modal.addEventListener('change', (event) => {
    if (event.target.name === 'dl-dest') applyMode();
    if (event.target === els.confirmCheck) refreshConfirmState();
  });

  if (els.search) {
    els.search.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(searchDestinations, 200);
    });
  }

  modal.addEventListener('click', (event) => {
    if (event.target === modal || event.target.closest('[data-design-library-destination-close]')) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
  if (els.confirm) els.confirm.addEventListener('click', submit);
}

function boot() {
  const shell = document.querySelector('main.dl-shell');
  if (shell) initDestinations(shell);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
