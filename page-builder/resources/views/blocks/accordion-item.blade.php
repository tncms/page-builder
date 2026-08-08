{{--
    Accordion item block (Phase 4). A native <details>/<summary> disclosure —
    no JavaScript needed. Question + answer are escaped; renders nothing when
    both are empty, and omits the answer when it is empty.
--}}
@php
    $question = \Plugins\PageBuilder\Support\Localize::text($data['question'] ?? '');
    $answer = \Plugins\PageBuilder\Support\Localize::text($data['answer'] ?? '');
@endphp
@if ($question !== '' || $answer !== '')
    <details class="pb-block pb-block--accordion-item pb-accordion-item">
        <summary class="pb-accordion-item__question">{{ $question }}</summary>
        @if ($answer !== '')
            <div class="pb-accordion-item__answer">{!! nl2br(e($answer)) !!}</div>
        @endif
    </details>
@endif
