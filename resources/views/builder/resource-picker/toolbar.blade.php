{{--
    Builder Resource Picker toolbar (Phase 26.47B).

    Rendered inside the Page Builder editor page as a SIBLING of the Vue editor —
    it never touches the editor bundle, Builder state, the CommandBus, or
    serialization. It provides:
      - a config element carrying the per-context picker URLs + event names;
      - a "Use Templates" toolbar button (full-page context; Phase 26.47D);
      - the bridge script that opens the Design Library in Picker mode.

    Section/Block picking is opened by the Builder itself via
    window.TNPageBuilderResourcePicker.open('section'|'block').

    @var string $ownerType
    @var string $ownerId
--}}
@php
    use Plugins\PageBuilder\Builder\ResourcePicker\BuilderResourcePicker;

    $pickerBoot = app(BuilderResourcePicker::class)->bootData($ownerType ?? null, $ownerId ?? null);
    $canEdit = function_exists('cms_can') ? (bool) cms_can('page-builder.edit') : true;
    $pageUrl = $pickerBoot['contexts']['page'] ?? null;

    $pickerScriptUrl = \Illuminate\Support\Facades\Route::has('page-builder.builder.resource-picker.script')
        ? route('page-builder.builder.resource-picker.script')
        : url('page-builder/builder/resource-picker.js');
@endphp

@if ($canEdit)
    <style>
        .pb-picker-toolbar { position: fixed; right: 1rem; bottom: 1rem; z-index: 60; display: flex; gap: .5rem; }
        .pb-picker-toolbar__btn { border: 1px solid #262b36; background: #6ea8fe; color: #0b1220;
            border-radius: 10px; padding: .55rem .95rem; font: 600 14px/1 system-ui, sans-serif;
            cursor: pointer; box-shadow: 0 6px 18px rgba(0,0,0,.35); }
        .pb-picker-toolbar__btn:hover { filter: brightness(1.05); }
    </style>

    <div data-pb-resource-picker
        data-page-url="{{ $pageUrl }}"
        data-section-url="{{ $pickerBoot['contexts']['section'] }}"
        data-block-url="{{ $pickerBoot['contexts']['block'] }}"
        data-insert-event="{{ $pickerBoot['insertEvent'] }}"
        data-imported-event="{{ $pickerBoot['importedEvent'] }}"
        hidden></div>

    @if ($pageUrl)
        <div class="pb-picker-toolbar" role="toolbar" aria-label="{{ tn_trans('Design tools') }}">
            <button type="button" class="pb-picker-toolbar__btn" data-pb-import-design>
                {{ tn_trans('Use Templates') }}
            </button>
        </div>
    @endif

    <script type="module" src="{{ $pickerScriptUrl }}"></script>
@endif
