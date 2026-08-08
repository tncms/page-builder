{{--
    Metadata edit dialog (Phase 26.49, Rules 3/20).

    Edits ONLY descriptive metadata — name, description, category, tags,
    visibility and version notes. It deliberately never exposes the normalized
    payload, RID, fingerprint or compatibility: those are system-managed and
    immutable. To change the design's content the user opens it in the Builder and
    saves it as a new design (a new Capture), never an in-place payload edit.

    @var array $visibilities  allowed visibility values
--}}
@php($visibilities = $visibilities ?? ['private', 'unlisted'])
<div class="dl-modal" data-metadata-dialog role="dialog" aria-modal="true" aria-labelledby="dl-md-title" hidden>
    <div class="dl-modal__panel">
        <div class="dl-modal__bar">
            <strong id="dl-md-title">{{ tn_trans('Edit Metadata') }}</strong>
            <button type="button" class="dl-btn" data-metadata-close aria-label="{{ tn_trans('Cancel') }}">✕</button>
        </div>
        <form data-metadata-form style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .85rem;">
            <input type="hidden" name="rid" data-field="rid">

            <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                {{ tn_trans('Name') }}
                <input type="text" name="name" data-field="name" required maxlength="160"
                    style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;">
            </label>

            <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                {{ tn_trans('Description') }}
                <textarea name="description" data-field="description" rows="3" maxlength="2000"
                    style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;resize:vertical;"></textarea>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                    {{ tn_trans('Category') }}
                    <input type="text" name="category" data-field="category"
                        style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;">
                </label>
                <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                    {{ tn_trans('Visibility') }}
                    <select name="visibility" data-field="visibility"
                        style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;">
                        @foreach ($visibilities as $vis)
                            <option value="{{ $vis }}">{{ ucfirst($vis) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                {{ tn_trans('Tags') }} <span style="text-transform:none;font-size:.72rem;">({{ tn_trans('comma separated') }})</span>
                <input type="text" name="tags" data-field="tags" placeholder="hero, cta, dark"
                    style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;">
            </label>

            <label style="display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:var(--dl-muted);">
                {{ tn_trans('Version Notes') }}
                <textarea name="versionNotes" data-field="versionNotes" rows="2" maxlength="2000"
                    style="background:var(--dl-bg);color:var(--dl-text);border:1px solid var(--dl-line);border-radius:8px;padding:.55rem .6rem;font:inherit;resize:vertical;"></textarea>
            </label>

            <p data-metadata-error role="alert" style="display:none;margin:0;color:#ff9aa8;font-size:.82rem;"></p>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button type="button" class="dl-btn" data-metadata-close>{{ tn_trans('Cancel') }}</button>
                <button type="submit" class="dl-btn dl-btn--primary" data-metadata-save>{{ tn_trans('Save') }}</button>
            </div>
        </form>
    </div>
</div>
