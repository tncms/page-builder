<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

/**
 * A Page Builder component definition (Phase 6 — Schema-Driven Definitions).
 *
 * The object-oriented description of a section or block: its identity, editor
 * metadata (label / category / icon), default data, the field schema the future
 * Vue editor renders, capability flags, and any assets. `toArray()` flattens all
 * of this into the plain, normalized array shape the registries store — so the
 * registry stays array-based and backward-compatible while authors get a typed,
 * discoverable builder API.
 */
interface ComponentDefinition
{
    /** Stable machine identifier, e.g. "hero" or "heading". */
    public function type(): string;

    /** Human label shown in the editor palette. */
    public function label(): string;

    /** Grouping category for the palette, e.g. "layout" / "basic". */
    public function category(): string;

    /** Optional icon identifier (heroicon name), or null. */
    public function icon(): ?string;

    /**
     * Default data map seeded when the component is inserted.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * The field schema (tabs → fields) the editor renders, already flattened to
     * arrays: {tabs: [...]}.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * Capability flags, e.g. {blocks: [...allowed child types], align: true}.
     *
     * @return array<string, mixed>
     */
    public function supports(): array;

    /**
     * Frontend + editor assets: {css, js, editor_css, editor_js}.
     *
     * @return array{css: array<int, string>, js: array<int, string>, editor_css: array<int, string>, editor_js: array<int, string>}
     */
    public function assets(): array;

    /**
     * Editor-only metadata (insert behaviour, preview hints, …).
     *
     * @return array<string, mixed>
     */
    public function editor(): array;

    /**
     * Flatten the definition to the normalized array the registries store.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
