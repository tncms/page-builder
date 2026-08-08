// Phase 18 — A single inline-edit session (pure logic, no DOM/Vue).
//
// Holds the target descriptor + the working value, and knows how to fold that
// value back into the component's data map for the update-component-data command
// — including translatable fields, where only the ACTIVE locale is written and
// the other locales are preserved.

import type { InlineTarget } from './SelectionManager'

export class InlineEditor {
  value: string

  readonly original: string

  constructor(public readonly target: InlineTarget) {
    this.value = target.value
    this.original = target.value
  }

  setValue(value: string): void {
    this.value = value
  }

  get dirty(): boolean {
    return this.value !== this.original
  }

  /**
   * Merge the edited field into a clone of the component's current data. For a
   * translatable field the value is written under `locale`, keeping any other
   * locales that were already present.
   */
  buildData(currentData: Record<string, unknown>, locale: string): Record<string, unknown> {
    const next: Record<string, unknown> = { ...currentData }
    const field = this.target.field

    if (this.target.translatable) {
      const existing = currentData[field]
      const map =
        existing && typeof existing === 'object' && !Array.isArray(existing)
          ? { ...(existing as Record<string, unknown>) }
          : {}
      map[locale] = this.value
      next[field] = map
    } else {
      next[field] = this.value
    }

    return next
  }
}
