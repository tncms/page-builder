<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Contracts;

use Plugins\PageBuilder\Patterns\PatternManifest;

/**
 * A Pattern is a higher-level, data-driven authoring unit built ON TOP of the
 * existing component system: a manifest (metadata) plus a Builder payload (a real
 * layout payload — never a new format). Payloads are lazy so a large library can
 * list manifests cheaply and only materialise a payload on preview / insert.
 */
interface PatternInterface
{
    public function id(): string;

    public function manifest(): PatternManifest;

    /**
     * The Builder payload for this pattern (lazy-loaded). Shape matches the layout
     * payload the editor already uses: ['sections' => [...], 'version' => int].
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
