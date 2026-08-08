// Phase 26.28 — Template Starter Pack contracts (grouping, not a new runtime concept).
//
// A starter pack is a named, versioned GROUP of templates (referenced by id) — a curated bundle a
// site can adopt. It does not own or store templates: `templates` are ids that continue to live in
// their own sources; deleting a pack never deletes a template. Reuses the Phase 26.27
// `PackageCompatibility` contract for its optional requirements. No ownership / pricing / licensing
// / remote / marketplace fields.

import type { PackageCompatibility } from '../package/templatePackageTypes'

/** Where a starter pack comes from (invariant KEYS, never localized). */
export const STARTER_PACK_SOURCE = {
  builtin: 'builtin',
  local: 'local',
  imported: 'imported',
  // Reserved future keys — no provider here.
  marketplace: 'marketplace',
  remote: 'remote',
  cloud: 'cloud',
} as const
export type StarterPackSource = (typeof STARTER_PACK_SOURCE)[keyof typeof STARTER_PACK_SOURCE]

/** A curated group of templates. `templates` are template ids (references, not definitions). */
export interface TemplateStarterPack {
  id: string
  name: string
  version: string
  description?: string
  /** An invariant category KEY (never a localized label). */
  category: string
  /** Template ids this pack groups (references — the pack does not own them). */
  templates: string[]
  /** A thumbnail URL — never a media/attachment id. */
  thumbnail?: string
  /** Source KEY (defaults to 'builtin' when registered from bundled data). */
  source?: string
  /** Optional Phase 26.27 compatibility requirements. */
  compatibility?: PackageCompatibility
}

/** A single starter-pack problem. */
export interface StarterPackIssue {
  code: string
  message: string
}

/** Thrown when a starter pack fails validation or violates registry identity rules. */
export class StarterPackError extends Error {
  readonly issues: StarterPackIssue[]
  constructor(message: string, issues: StarterPackIssue[]) {
    super(message)
    this.name = 'StarterPackError'
    this.issues = issues
  }
}
