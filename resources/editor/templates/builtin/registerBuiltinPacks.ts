// Phase 26.25 — boot registration for the bundled built-in packs.
//
// Registers the first-party packs into the definition registry (→ resolvable + applyable) and,
// by default, publishes their metadata to the Phase 26.21 built-in library (→ discoverable in the
// Picker). Called once at editor boot. NOT a top-level side effect — importing this module
// registers nothing, so tests and other consumers stay deterministic.

import { builtinTemplatePackRegistry, type BuiltinTemplatePackRegistry } from './builtinPackRegistry'
import type { ValidatePackOptions } from './packValidation'
import type { BuiltinTemplatePack } from './builtinPackTypes'
import type { TemplateDefinition } from '../../contracts'
import { builtinTemplateLibrary, toLibraryItem, TEMPLATE_SOURCE } from '../library'
import { companyPack } from './packs/company/companyPack'

/** The packs shipped with the editor. Add first-party packs here. */
export const BUILTIN_TEMPLATE_PACKS: readonly BuiltinTemplatePack[] = [companyPack]

/**
 * Admin-UI-locale display labels for built-in templates, keyed by template id
 * (PB-FREE-LIBRARY-DESIGN-1-F). Supplied by the server boot payload; applied over the
 * definition's invariant English metadata when publishing to the discoverable library.
 */
export type BuiltinTemplateLabels = Record<string, { name?: string; description?: string } | undefined>

export interface RegisterBuiltinPacksOptions {
  registry?: BuiltinTemplatePackRegistry
  /** Passed to validation (e.g. editor metadata for strict type checks). */
  validate?: ValidatePackOptions
  /** Publish pack templates to the built-in library for discovery (default true). */
  publishToLibrary?: boolean
  /** Freeze the registry after registering (immutable after boot; default false). */
  freeze?: boolean
  /** Admin-UI-locale name/description labels applied to published library items. */
  labels?: BuiltinTemplateLabels
}

/**
 * Apply an Admin-UI-locale label over a definition's invariant metadata for library
 * publication only. The template id/layout/schema are never touched — only the human
 * name/description shown in the Picker follow the Admin UI locale.
 */
function localizeDefinition(definition: TemplateDefinition, labels?: BuiltinTemplateLabels): TemplateDefinition {
  const label = labels?.[definition.id]
  if (!label) return definition

  const name = typeof label.name === 'string' && label.name !== '' ? label.name : definition.name
  const description =
    typeof label.description === 'string' && label.description !== '' ? label.description : definition.description
  return { ...definition, name, description }
}

/** Register every bundled pack. Idempotent by pack id. Throws `BuiltinPackError` on an invalid pack. */
export function registerBuiltinPacks(options: RegisterBuiltinPacksOptions = {}): void {
  const registry = options.registry ?? builtinTemplatePackRegistry
  const publish = options.publishToLibrary !== false

  for (const pack of BUILTIN_TEMPLATE_PACKS) {
    const result = registry.register(pack, options.validate)
    if (publish && !result.skipped) {
      for (const definition of pack.definitions) {
        builtinTemplateLibrary.register(
          toLibraryItem(localizeDefinition(definition, options.labels), TEMPLATE_SOURCE.builtin),
        )
      }
    }
  }

  if (options.freeze) registry.freeze()
}
