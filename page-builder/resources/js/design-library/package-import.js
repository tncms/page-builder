/**
 * Design Library — package export/import (Phase 26.47), progressive enhancement.
 *
 * Adds two transport actions on top of the server-rendered library:
 *   - Export: download a resource as a portable .tncms-design package.
 *   - Import: pick a .tncms-design file, validate it server-side, then import it.
 *     Imported resources appear in the library with source=imported.
 *
 * It never renders resource HTML, never touches Builder serialization, the
 * CommandBus, or editor state. Packages are transport only; validation and
 * persistence happen server-side.
 */
const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

function shell() {
  return document.querySelector('main.dl-shell');
}

function postJson(url, body) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  }).then((r) => r.json().then((data) => ({ status: r.status, data })));
}

function initExport(root) {
  const exportUrl = root.getAttribute('data-package-export-url');
  if (!exportUrl) return;

  root.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-design-library-export]');
    if (!btn) return;
    const card = btn.closest('[data-design-library-card]');
    const rid = card && card.getAttribute('data-rid');
    if (!rid) return;

    // Trigger a download without navigating away from the library.
    const url = exportUrl + (exportUrl.indexOf('?') === -1 ? '?' : '&') + 'rid=' + encodeURIComponent(rid);
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });
}

function initImport(root) {
  const modal = document.querySelector('[data-design-library-package]');
  const validateUrl = root.getAttribute('data-package-validate-url');
  const importUrl = root.getAttribute('data-package-import-url');
  if (!modal || !validateUrl || !importUrl) return;

  const fileInput = modal.querySelector('[data-design-library-package-file]');
  const summary = modal.querySelector('[data-package-summary]');
  const meta = modal.querySelector('[data-package-meta]');
  const nameEl = modal.querySelector('[data-package-name]');
  const countEl = modal.querySelector('[data-package-count]');
  const warnings = modal.querySelector('[data-package-warnings]');
  const blockers = modal.querySelector('[data-package-blockers]');
  const confirmBtn = modal.querySelector('[data-design-library-package-confirm]');
  let content = '';
  let lastFocus = null;

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

  const reset = () => {
    content = '';
    if (summary) summary.textContent = 'Select a package to validate it before importing.';
    if (meta) meta.hidden = true;
    renderIssues(warnings, []);
    renderIssues(blockers, []);
    if (confirmBtn) confirmBtn.disabled = true;
    if (fileInput) fileInput.value = '';
  };

  const open = () => {
    lastFocus = document.activeElement;
    reset();
    modal.hidden = false;
    if (fileInput) fileInput.focus();
  };

  const close = () => {
    modal.hidden = true;
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  };

  const showReport = (report) => {
    if (nameEl) nameEl.textContent = report.packageName || '—';
    if (countEl) countEl.textContent = String(report.resourceCount || 0);
    if (meta) meta.hidden = false;
    renderIssues(warnings, report.warnings);
    renderIssues(blockers, report.blockers);
    const importable = !(report.blockers && report.blockers.length);
    if (summary) {
      summary.textContent = importable
        ? 'This package is ready to import.'
        : 'This package cannot be imported.';
    }
    if (confirmBtn) confirmBtn.disabled = !importable;
  };

  root.addEventListener('click', (event) => {
    if (event.target.closest('[data-design-library-package-open]')) open();
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal || event.target.closest('[data-design-library-package-close]')) close();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });

  if (fileInput) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (summary) summary.textContent = 'Validating…';
      const reader = new FileReader();
      reader.onload = () => {
        content = String(reader.result || '');
        postJson(validateUrl, { content })
          .then(({ data }) => showReport((data && data.data) || {}))
          .catch(() => {
            if (summary) summary.textContent = 'Could not validate the package.';
          });
      };
      reader.onerror = () => {
        if (summary) summary.textContent = 'Could not read the file.';
      };
      reader.readAsText(file);
    });
  }

  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      if (!content) return;
      confirmBtn.disabled = true;
      if (summary) summary.textContent = 'Importing…';
      postJson(importUrl, { content })
        .then(({ data }) => {
          const report = (data && data.data) || {};
          if (report.imported) {
            if (summary) summary.textContent = 'Design imported successfully. Available in Imported designs.';
            document.dispatchEvent(new CustomEvent('page-builder:design-imported', { detail: { rids: report.rids || [] } }));
            // Reload so the imported resources appear (source=imported).
            setTimeout(() => window.location.reload(), 600);
          } else {
            renderIssues(blockers, report.blockers);
            if (summary) summary.textContent = 'Import did not complete.';
            confirmBtn.disabled = false;
          }
        })
        .catch(() => {
          if (summary) summary.textContent = 'Import failed.';
          confirmBtn.disabled = false;
        });
    });
  }
}

function boot() {
  const root = shell();
  if (!root) return;
  initExport(root);
  initImport(root);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
