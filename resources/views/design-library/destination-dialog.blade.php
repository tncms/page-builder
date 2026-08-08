{{--
    Page Template destination dialog (Phase 26.47D — Part A).

    Admin "Use This Template" workflow. Choose where to apply a full-page template:
      - Create New Page / Post — creates a Draft, applies the template to its
        Builder draft, and redirects to the editor.
      - Use Existing Page / Post — searches the selected owner type, shows the
        current/incoming counts, requires a destructive confirmation, then applies
        to the existing Builder draft (recovery snapshot + draft only).

    Application always goes through the existing FullPageImportService. The dialog
    is enhancement only (page-template.js drives it); it never mutates anything.
--}}
<div class="dl-modal" data-design-library-destination role="dialog" aria-modal="true" aria-labelledby="dl-dest-title" hidden>
    <div class="dl-modal__panel" style="width: min(560px, 100%);">
        <div class="dl-modal__bar">
            <strong id="dl-dest-title">Use This Template</strong>
            <button type="button" class="dl-btn" data-design-library-destination-close aria-label="Cancel">✕</button>
        </div>
        <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .9rem;">
            <p style="margin: 0; color: var(--dl-muted);" data-dest-template-name aria-live="polite"></p>

            <fieldset style="border: 1px solid var(--dl-line); border-radius: 10px; padding: .75rem .9rem; margin: 0;">
                <legend style="padding: 0 .4rem; color: var(--dl-muted); font-size: .8rem;">{{ tn_trans('Where would you like to use this template?') }}</legend>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .4rem .9rem;">
                    <label style="display:flex; gap:.5rem; align-items:center;"><input type="radio" name="dl-dest" value="create:page" checked> {{ tn_trans('Create New Page') }}</label>
                    <label style="display:flex; gap:.5rem; align-items:center;"><input type="radio" name="dl-dest" value="create:post"> {{ tn_trans('Create New Post') }}</label>
                    <label style="display:flex; gap:.5rem; align-items:center;"><input type="radio" name="dl-dest" value="existing:page"> {{ tn_trans('Use Existing Page') }}</label>
                    <label style="display:flex; gap:.5rem; align-items:center;"><input type="radio" name="dl-dest" value="existing:post"> {{ tn_trans('Use Existing Post') }}</label>
                </div>
            </fieldset>

            {{-- Create New: minimum metadata (title). Locale defaults server-side. --}}
            <div data-dest-create>
                <label style="display:flex; flex-direction:column; gap:.3rem; font-size:.85rem; color: var(--dl-muted);">
                    {{ tn_trans('Title') }}
                    <input type="text" data-dest-title placeholder="{{ tn_trans('Untitled') }}"
                           style="background: var(--dl-surface); color: var(--dl-text); border: 1px solid var(--dl-line); border-radius: 8px; padding: .55rem .6rem; font: inherit;">
                </label>
                <p style="margin: .5rem 0 0; font-size: .8rem; color: var(--dl-muted);">
                    {{ tn_trans('A new draft is created. Nothing is published until you choose to publish.') }}
                </p>
            </div>

            {{-- Use Existing: search a target, review the replacement, confirm. --}}
            <div data-dest-existing hidden>
                <label style="display:flex; flex-direction:column; gap:.3rem; font-size:.85rem; color: var(--dl-muted);">
                    {{ tn_trans('Search') }}
                    <input type="search" data-dest-search placeholder="{{ tn_trans('Search by title…') }}"
                           style="background: var(--dl-surface); color: var(--dl-text); border: 1px solid var(--dl-line); border-radius: 8px; padding: .55rem .6rem; font: inherit;">
                </label>
                <ul data-dest-results role="listbox" aria-label="{{ tn_trans('Destinations') }}"
                    style="list-style:none; margin:.5rem 0 0; padding:0; max-height: 180px; overflow:auto; display:flex; flex-direction:column; gap:.25rem;"></ul>

                <p data-dest-summary aria-live="polite" style="margin: .6rem 0 0; font-size: .82rem; color: var(--dl-muted);"></p>
                <label data-dest-confirm-row hidden style="display:flex; gap:.5rem; align-items:flex-start; margin-top:.5rem; font-size:.82rem; color:#ffcf70;">
                    <input type="checkbox" data-dest-confirm-check style="margin-top:.2rem;">
                    <span>{{ tn_trans('Replace the current Builder draft of the selected destination. Published content is unchanged until you publish; a recovery snapshot is created.') }}</span>
                </label>
            </div>

            <div data-dest-error role="alert" style="display:none; color:#ff9aa8; font-size:.85rem;"></div>

            <div style="display:flex; gap:.5rem; justify-content:flex-end;">
                <button type="button" class="dl-btn" data-design-library-destination-close>{{ tn_trans('Cancel') }}</button>
                <button type="button" class="dl-btn dl-btn--primary" data-design-library-destination-confirm disabled>{{ tn_trans('Use Template') }}</button>
            </div>
        </div>
    </div>
</div>
