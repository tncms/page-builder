{{--
    Archive / delete confirmation dialog (Phase 26.49, Rules 6/8).

    A shared confirm dialog for the destructive-ish lifecycle actions. Archive
    only hides a design from normal browsing and stays fully recoverable; delete
    is a SOFT delete — the resource is never permanently removed (a future cleanup
    job may purge it). The JS sets the copy and target action before showing it.
--}}
<div class="dl-modal" data-archive-dialog role="dialog" aria-modal="true" aria-labelledby="dl-ad-title" hidden>
    <div class="dl-modal__panel" style="width: min(460px, 100%);">
        <div class="dl-modal__bar">
            <strong id="dl-ad-title" data-archive-title>{{ tn_trans('Archive design') }}</strong>
            <button type="button" class="dl-btn" data-archive-close aria-label="{{ tn_trans('Cancel') }}">✕</button>
        </div>
        <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .85rem;">
            <p data-archive-message style="margin:0;color:var(--dl-muted);">
                {{ tn_trans('This design will be hidden from normal browsing. You can restore it any time.') }}
            </p>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" class="dl-btn" data-archive-close>{{ tn_trans('Cancel') }}</button>
                <button type="button" class="dl-btn dl-btn--primary" data-archive-confirm>{{ tn_trans('Confirm') }}</button>
            </div>
        </div>
    </div>
</div>
