// Phase 26.37 — the four built-in insert providers + default wiring.
//
// Each provider is created with an INJECTED `open` handler that launches its existing experience —
// this layer holds no insertion logic and no item registry. The default handlers wire the two
// categories with a clean store entrypoint (patterns → Pattern Picker, assets → Asset Picker). The
// blocks (component palette) and templates (Template Picker) experiences are opened by handlers the
// host supplies, since they have no store-level open toggle. Deterministic order: blocks → patterns
// → templates → assets.

import { usePatternPickerStore } from '../patterns/picker/patternPickerStore'
import { useAssetPickerStore } from '../assets'
import { INSERT_CATEGORY, INSERT_CATEGORY_ORDER, type InsertCategory } from './insertCategory'
import type { InsertProvider } from './InsertProvider'
import { InsertProviderRegistry } from './InsertProviderRegistry'

/** An `open` handler per category — each launches that category's existing experience. */
export type InsertOpenHandlers = Record<InsertCategory, () => void>

/** Localized labels + descriptions for the built-in categories. */
const BUILTIN_INSERT_META: Record<InsertCategory, { label: string; description: string }> = {
  [INSERT_CATEGORY.blocks]: { label: 'Blocks', description: 'Insert a single component.' },
  [INSERT_CATEGORY.patterns]: { label: 'Patterns', description: 'Insert a reusable section pattern.' },
  [INSERT_CATEGORY.templates]: { label: 'Templates', description: 'Start from a full page template.' },
  [INSERT_CATEGORY.assets]: { label: 'Assets', description: 'Pick an image or media asset.' },
}

/** Build one provider from its category + injected open handler. */
export function makeInsertProvider(key: InsertCategory, open: () => void): InsertProvider {
  const meta = BUILTIN_INSERT_META[key]
  return { key, label: meta.label, description: meta.description, open }
}

/**
 * Default open handlers. Patterns and Assets route to their existing store pickers; Blocks and
 * Templates default to no-ops (documented) — the host overrides them to toggle the component palette
 * / Template Picker, which have no store-level open action.
 */
export function defaultInsertOpenHandlers(): InsertOpenHandlers {
  return {
    blocks: () => {}, // host wires the component palette
    patterns: () => usePatternPickerStore().openPicker(),
    templates: () => {}, // host wires the Template Picker
    assets: () => void useAssetPickerStore().openPicker(),
  }
}

/** Register the four built-in providers into `registry` in deterministic order. */
export function registerBuiltinInsertProviders(
  registry: InsertProviderRegistry,
  handlers: InsertOpenHandlers,
): void {
  for (const key of INSERT_CATEGORY_ORDER) {
    registry.register(makeInsertProvider(key, handlers[key]))
  }
}

/** The default insert provider registry (populated by the host via `registerBuiltinInsertProviders`). */
export const insertProviderRegistry = new InsertProviderRegistry()
