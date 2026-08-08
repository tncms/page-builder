<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Contracts\ComponentInstance;
use Plugins\PageBuilder\Runtime\ComponentFactory;
use Plugins\PageBuilder\Runtime\ComponentRuntime;
use Plugins\PageBuilder\Runtime\ComponentTree;

/**
 * Server-side, lightweight renderer that turns a stored layout payload into HTML
 * (Phase 3; refactored onto the component runtime in Phase 7).
 *
 * The payload is normalized ({@see LayoutNormalizer}) and built into a
 * {@see ComponentTree} of instances, each rendered through the
 * {@see ComponentRuntime} (resolve lifecycle + theme-override → plugin-fallback
 * view chain + lifecycle hooks). The output is identical to the pre-runtime
 * renderer for the built-in library; there is still no Vue, no client editor —
 * the frontend stays server-rendered.
 */
class LayoutRenderer
{
    public function __construct(
        private readonly LayoutNormalizer $normalizer,
        private readonly ComponentFactory $factory,
        private readonly ComponentRuntime $runtime,
    ) {}

    public function normalizer(): LayoutNormalizer
    {
        return $this->normalizer;
    }

    public function runtime(): ComponentRuntime
    {
        return $this->runtime;
    }

    /**
     * Build the component tree for a (nullable/partial) layout payload.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function tree(?array $payload): ComponentTree
    {
        return ComponentTree::fromPayload($payload, $this->normalizer, $this->factory);
    }

    /**
     * Render a (possibly null/partial) layout payload to HTML.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function render(?array $payload): string
    {
        $html = '';
        foreach ($this->tree($payload)->sections() as $section) {
            $html .= $this->runtime->render($section);
        }

        return $html;
    }

    /**
     * Render a payload with editor markers around every component (Phase 12).
     *
     * Toggles the runtime into editor mode for the duration of this render only,
     * so public {@see render()} output stays byte-for-byte identical. The `finally`
     * guarantees the (singleton) runtime is reset even if a view throws.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function renderForEditor(?array $payload): string
    {
        $this->runtime->setEditorMode(true);

        try {
            return $this->render($payload);
        } finally {
            $this->runtime->setEditorMode(false);
        }
    }

    /**
     * Render a SINGLE component (by id) from a full payload, with editor markers
     * (Phase 21 — Live Component Runtime).
     *
     * The full payload is built into a {@see ComponentTree} so the target's
     * lifecycle (defaults → responsive → tokens → dynamic → conditions) resolves
     * exactly as it would in a whole-layout render; the located instance is then
     * rendered in editor mode, so its output (including any children a section /
     * container block composes) is wrapped in the same `.pb-editor-render-marker`
     * the visual canvas maps by id. Returns null when the id is blank or no
     * component matches — the caller (endpoint) turns that into a 404. Toggles
     * editor mode for this render only, restoring it in `finally`, so public
     * {@see render()} output stays byte-for-byte identical.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function renderComponentForEditor(?array $payload, string $componentId): ?string
    {
        if (trim($componentId) === '') {
            return null;
        }

        $instance = $this->tree($payload)->find(
            static fn (ComponentInstance $instance): bool => $instance->id() === $componentId,
        );

        if ($instance === null) {
            return null;
        }

        $this->runtime->setEditorMode(true);

        try {
            return $this->runtime->render($instance);
        } finally {
            $this->runtime->setEditorMode(false);
        }
    }

    /**
     * Render a list of raw (un-normalized) child blocks — used by container
     * blocks such as `column` to render the blocks nested inside their own data.
     * Non-array or type-less children are skipped; each valid child is built into
     * a block instance and rendered through the runtime.
     *
     * @param  mixed  $blocks
     */
    public function renderChildren(mixed $blocks): string
    {
        if (! is_array($blocks)) {
            return '';
        }

        $html = '';
        foreach ($blocks as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = $node['type'] ?? null;
            if (! is_string($type) || trim($type) === '') {
                continue;
            }

            $html .= $this->runtime->render($this->factory->block($node));
        }

        return $html;
    }
}
