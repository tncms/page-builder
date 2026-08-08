// Phase 26.27 — pure compatibility checks (validation only; nothing is mutated or migrated).
//
// `checkPackageCompatibility` decides whether a package can be imported into a given builder
// environment. It only detects INCOMPATIBILITY and MISSING capabilities — it never rewrites a
// package. A package with no `compatibility` block is unconstrained (compatible). Unverifiable
// requirements (declared but the environment lists no known set) become non-blocking warnings.

import { SUPPORTED_PACKAGE_SCHEMAS, type PackageCompatibility, type TemplatePackage } from '../package/templatePackageTypes'
import {
  BUILDER_VERSION,
  SUPPORTED_TEMPLATE_SCHEMA_VERSIONS,
  type BuilderEnvironment,
  type CompatibilityIssue,
  type CompatibilityResult,
  type TemplateCompatibility,
} from './compatibilityTypes'
import { isExactVersion, isValidRange, satisfiesRange } from './versionRange'

/** The default environment: current builder, package module's supported schemas, no capability sets. */
export const defaultBuilderEnvironment: BuilderEnvironment = {
  builderVersion: BUILDER_VERSION,
  supportedPackageSchemas: SUPPORTED_PACKAGE_SCHEMAS,
}

/** Validate a standalone version-metadata contract. Empty array = valid. */
export function validateTemplateCompatibility(meta: TemplateCompatibility): CompatibilityIssue[] {
  const issues: CompatibilityIssue[] = []
  if (!(SUPPORTED_TEMPLATE_SCHEMA_VERSIONS as readonly string[]).includes(meta?.schemaVersion)) {
    issues.push({ code: 'unknown-schema', message: `Unknown compatibility schema "${meta?.schemaVersion}".` })
  }
  if (!meta?.templateVersion || !isExactVersion(meta.templateVersion)) {
    issues.push({ code: 'invalid-version', message: `Invalid template version "${meta?.templateVersion}".` })
  }
  if (meta?.builderVersion !== undefined && !isValidRange(meta.builderVersion)) {
    issues.push({ code: 'invalid-builder', message: `Invalid builder version/range "${meta.builderVersion}".` })
  }
  return issues
}

/** Decide whether a package is compatible with an environment. Pure — reasons/warnings only. */
export function checkPackageCompatibility(
  pkg: TemplatePackage,
  environment: BuilderEnvironment = defaultBuilderEnvironment,
): CompatibilityResult {
  const reasons: CompatibilityIssue[] = []
  const warnings: CompatibilityIssue[] = []
  const schemas = environment.supportedPackageSchemas ?? SUPPORTED_PACKAGE_SCHEMAS

  if (!schemas.includes(pkg?.schema)) {
    reasons.push({ code: 'unsupported-schema', message: `Unknown package schema "${pkg?.schema}".` })
  }

  const req = checkCompatibilityRequirements(pkg?.compatibility, environment)
  reasons.push(...req.reasons)
  warnings.push(...req.warnings)

  return { compatible: reasons.length === 0, reasons, warnings }
}

/**
 * The builder-range + required-component/feature portion of a compatibility check, decoupled from
 * the package schema so any consumer (e.g. a starter pack) can reuse the SAME rules — there is one
 * compatibility system. Returns reasons (blocking) + warnings (non-blocking).
 */
export function checkCompatibilityRequirements(
  compatibility: PackageCompatibility | undefined,
  environment: BuilderEnvironment = defaultBuilderEnvironment,
): { reasons: CompatibilityIssue[]; warnings: CompatibilityIssue[] } {
  const reasons: CompatibilityIssue[] = []
  const warnings: CompatibilityIssue[] = []

  if (compatibility?.builder) {
    if (!isValidRange(compatibility.builder)) {
      reasons.push({ code: 'invalid-builder-range', message: `Invalid builder range "${compatibility.builder}".` })
    } else if (!satisfiesRange(environment.builderVersion, compatibility.builder)) {
      reasons.push({
        code: 'builder-incompatible',
        message: `Requires builder ${compatibility.builder}; this builder is ${environment.builderVersion}.`,
      })
    }
  }

  collectMissing('component', compatibility?.requiredComponents, environment.components, reasons, warnings)
  collectMissing('feature', compatibility?.requiredFeatures, environment.features, reasons, warnings)

  return { reasons, warnings }
}

/** Required capabilities: missing-from-a-known-set → blocking reason; no known set → warning. */
function collectMissing(
  kind: 'component' | 'feature',
  required: string[] | undefined,
  known: ReadonlySet<string> | undefined,
  reasons: CompatibilityIssue[],
  warnings: CompatibilityIssue[],
): void {
  if (!required?.length) return
  if (!known) {
    warnings.push({ code: `${kind}s-unverified`, message: `Required ${kind}s could not be verified in this environment.` })
    return
  }
  for (const key of required) {
    if (!known.has(key)) reasons.push({ code: `missing-${kind}`, message: `Missing required ${kind} "${key}".` })
  }
}
