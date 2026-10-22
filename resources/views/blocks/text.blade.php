{{--
    Reference "text" block (Phase 3).

    A minimal, XSS-safe block view demonstrating the render contract: $data is
    the block's normalized data map. User content is echoed escaped ({{ }}),
    never raw.
--}}
<div class="pb-block pb-block--text{{ in_array($data['variant'] ?? 'default', ['eyebrow', 'lead'], true) ? ' pb-text--'.$data['variant'] : '' }}">{{ \Plugins\PageBuilder\Support\Localize::text($data['text'] ?? '') }}</div>
