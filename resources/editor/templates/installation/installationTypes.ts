// Phase 26.30 — Template Pack Installation contracts (adoption, not execution).
//
// Installing a pack is an ADOPTION action: it records that a site has taken up a starter pack and
// validates that the pack's referenced templates are available in their existing source. It does
// NOT copy template definitions, create a second template registry, apply templates, run commands,
// or touch BuilderState. An installed pack holds REFERENCES (template ids), never definitions —
// resolution stays with the Phase 26.24 sources. No marketplace / purchase / licensing / remote.

/** An installed pack: an availability record of references, never a copy of any template. */
export interface InstalledPack {
  /** Invariant installation identity: `<source>:<packId>`. */
  id: string
  /** The underlying `TemplateStarterPack.id` (bare, invariant). */
  packId: string
  /** The invariant source KEY the pack came from. */
  source: string
  name: string
  category: string
  /** The template ids the pack makes available — REFERENCES into the existing sources. */
  templateIds: string[]
}

/** A single installation problem. */
export interface InstallationIssue {
  code: string
  message: string
}

/** The outcome of an install attempt. `ok` with `alreadyInstalled` = a safe idempotent no-op. */
export interface InstallationResult {
  ok: boolean
  installed: InstalledPack | null
  alreadyInstalled: boolean
  reasons: InstallationIssue[]
}

/** The minimal installation contract. */
export interface TemplatePackInstaller {
  install(packId: string): InstallationResult
}

/**
 * Map a starter-pack source KEY to the Phase 26.24 DEFINITION source its templates resolve through:
 * builtin → builtin, local → user, imported → imported. Reserved/unknown sources → null (rejected).
 */
export function packSourceToDefinitionSource(packSource: string): string | null {
  switch (packSource) {
    case 'builtin':
      return 'builtin'
    case 'local':
      return 'user'
    case 'imported':
      return 'imported'
    default:
      return null
  }
}
