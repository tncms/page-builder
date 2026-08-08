<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\ResourcePicker;

/**
 * The two operating modes of the Design Library (Phase 26.47B).
 *
 * The same repository, the same cards, and the same Runtime Preview back both
 * modes — only the available card actions differ:
 *
 *   - Admin  → manage resources (preview, export, package import, metadata).
 *   - Picker → select a resource for the Builder (preview + one context action).
 *
 * Admin is the default; the Builder opens the library in Picker mode.
 */
enum PickerMode: string
{
    case Admin = 'admin';
    case Picker = 'picker';

    public static function fromRequest(?string $value): self
    {
        return $value === self::Picker->value ? self::Picker : self::Admin;
    }

    public function isPicker(): bool
    {
        return $this === self::Picker;
    }
}
