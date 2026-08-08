{{--
    Design Library page header (Phase 26.47A; Picker mode 26.47B).

    Title, a short resource-oriented subheading, the live result count, and — in
    Admin mode only — the Import Package (.tncms-design) action. In Picker mode the
    subheading names the Builder context and the admin package action is hidden.

    @var int         $count       cards currently shown
    @var int         $total       total resources available
    @var bool|null   $isPicker    whether the library is scoped as a Builder picker
    @var string|null $pickerKind  the locked kind in Picker mode
--}}
@php
    $isPicker = $isPicker ?? false;
    $pickerKind = $pickerKind ?? null;
    $pickerSub = match ($pickerKind) {
        'page' => tn_trans('Choose a full-page design to import into the current draft.'),
        'section' => tn_trans('Choose a section to insert into your page.'),
        'block' => tn_trans('Choose a block to insert into your page.'),
        default => tn_trans('Choose a design resource.'),
    };
@endphp
<header class="dl-head">
    <div>
        <h1>{{ tn_trans('Design Library') }}</h1>
        @if ($isPicker)
            <p class="dl-sub">{{ $pickerSub }}</p>
            <p class="dl-count" aria-live="polite">{{ $count }} {{ tn_trans('of') }} {{ $total }} {{ tn_trans('resources') }}</p>
        @else
            <p class="dl-sub">{{ tn_trans('Browse, preview, import and export reusable design resources.') }}</p>
            <p class="dl-count" aria-live="polite">
                {{ $count }} {{ tn_trans('of') }} {{ $total }} {{ tn_trans('resources') }}
                <span aria-hidden="true">·</span>
                {{ tn_trans('Blocks') }} • {{ tn_trans('Sections') }} • {{ tn_trans('Full Pages') }}
            </p>
        @endif
    </div>
    @unless ($isPicker)
        <div class="dl-head__actions">
            <button type="button" class="dl-btn" data-design-library-package-open>{{ tn_trans('Import Package') }}</button>
        </div>
    @endunless
</header>
