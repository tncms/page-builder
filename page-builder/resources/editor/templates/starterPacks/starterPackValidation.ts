// Phase 26.28 — starter pack validation (validation-first, deterministic).
//
// Validates a pack's metadata + template references + compatibility BEFORE registration. Reuses
// the Phase 26.27 compatibility system (`checkCompatibilityRequirements`) — there is no second
// compatibility layer. Reference checking is best-effort: only enforced when a set of known
// template ids is supplied (permissive otherwise), so the registry stays decoupled from where
// templates live. Returns blocking issues; compatibility warnings are surfaced by the registry.

import { isSafeAssetUrl } from '../../assets'
import { checkCompatibilityRequirements, isExactVersion, type BuilderEnvironment } from '../compatibility'
import type { StarterPackIssue, TemplateStarterPack } from './starterPackTypes'

export interface ValidateStarterPackOptions {
  /** Pack ids already registered — a clash is a duplicate. */
  knownPackIds?: ReadonlySet<string>
  /** Known template ids — when provided, missing references are reported. */
  knownTemplateIds?: ReadonlySet<string>
  /** Builder environment for the compatibility check. */
  environment?: BuilderEnvironment
}

/** Validate a starter pack. Empty array = valid. */
export function validateStarterPack(pack: TemplateStarterPack, options: ValidateStarterPackOptions = {}): StarterPackIssue[] {
  const issues: StarterPackIssue[] = []

  if (!pack || !pack.id?.trim()) {
    return [{ code: 'invalid-pack', message: 'Starter pack is missing an id.' }]
  }
  if (!pack.name?.trim()) issues.push({ code: 'invalid-pack', message: `Pack "${pack.id}" is missing a name.` })
  if (!isExactVersion(pack.version ?? '')) issues.push({ code: 'invalid-version', message: `Pack "${pack.id}" has an invalid version "${pack.version}".` })
  if (!pack.category?.trim()) issues.push({ code: 'invalid-category', message: `Pack "${pack.id}" is missing a category.` })
  if (options.knownPackIds?.has(pack.id)) issues.push({ code: 'duplicate-pack', message: `Pack "${pack.id}" is already registered.` })
  if (pack.thumbnail && !isSafeAssetUrl(pack.thumbnail)) issues.push({ code: 'unsafe-thumbnail', message: `Pack "${pack.id}" thumbnail is not a safe URL.` })

  const templates = pack.templates ?? []
  if (!Array.isArray(templates) || templates.length === 0) {
    issues.push({ code: 'empty-pack', message: `Pack "${pack.id}" references no templates.` })
  } else {
    if (new Set(templates).size !== templates.length) {
      issues.push({ code: 'duplicate-reference', message: `Pack "${pack.id}" references a template id more than once.` })
    }
    if (options.knownTemplateIds) {
      for (const id of templates) {
        if (!options.knownTemplateIds.has(id)) {
          issues.push({ code: 'missing-template', message: `Pack "${pack.id}" references unknown template "${id}".` })
        }
      }
    }
  }

  // Compatibility (Phase 26.27) — blocking reasons only; warnings are surfaced by the registry.
  const req = checkCompatibilityRequirements(pack.compatibility, options.environment)
  issues.push(...req.reasons)

  return issues
}
