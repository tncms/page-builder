{{--
    Design Package import dialog (Phase 26.47).

    A file picker + validation summary for portable .tncms-design packages. The
    enhancement module (package-import.js) drives it: pick a file → validate →
    review the summary → import. Imported resources appear in the library with
    source=imported. No runtime is involved — packages are transport only.
--}}
<div class="dl-modal" data-design-library-package role="dialog" aria-modal="true" aria-labelledby="dl-package-title" hidden>
    <div class="dl-modal__panel" style="width: min(600px, 100%);">
        <div class="dl-modal__bar">
            <strong id="dl-package-title">Import Package</strong>
            <button type="button" class="dl-btn" data-design-library-package-close aria-label="Cancel import">✕</button>
        </div>
        <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .8rem;">
            <label class="dl-fav-only" style="align-self: stretch;">
                Choose a <code>.{{ 'tncms-design' }}</code> package
                <input type="file" accept=".tncms-design,application/json" data-design-library-package-file
                       style="margin-top:.4rem;">
            </label>

            <div data-package-summary aria-live="polite" style="color: var(--dl-muted);">
                Select a package to validate it before importing.
            </div>

            <dl data-package-meta hidden style="margin:0; display:grid; grid-template-columns:auto 1fr; gap:.25rem .75rem; font-size:.85rem;">
                <dt style="color:var(--dl-muted);">Package</dt><dd data-package-name style="margin:0;"></dd>
                <dt style="color:var(--dl-muted);">Resources</dt><dd data-package-count style="margin:0;"></dd>
            </dl>

            <div data-package-warnings role="status" style="display:none; color:#ffcf70; font-size:.85rem;"></div>
            <div data-package-blockers role="alert" style="display:none; color:#ff9aa8; font-size:.85rem;"></div>

            <div style="display:flex; gap:.5rem; justify-content:flex-end;">
                <button type="button" class="dl-btn" data-design-library-package-close>Cancel</button>
                <button type="button" class="dl-btn dl-btn--primary" data-design-library-package-confirm disabled>Import Package</button>
            </div>
        </div>
    </div>
</div>
