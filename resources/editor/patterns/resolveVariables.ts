// Phase 25.1 — client-side variable helpers for the insert dialog. These drive the
// LIVE PREVIEW and form validation only; the authoritative interpolation of the
// payload template happens server-side (PatternVariableResolver) on insert. Kept
// deliberately tiny and pure so it is trivially unit-testable.
import type { PatternVariable, PatternVariableValues } from './types'

/** A variable's effective default (its declared default, else a type-appropriate empty). */
export function variableDefault(variable: PatternVariable): unknown {
  if (variable.default !== undefined && variable.default !== null) return variable.default
  switch (variable.type) {
    case 'toggle':
      return false
    case 'number':
      return 0
    default:
      return ''
  }
}

/** Build the initial value map for a variable set (all defaults). */
export function initialVariableValues(variables: PatternVariable[]): PatternVariableValues {
  const out: PatternVariableValues = {}
  for (const variable of variables) out[variable.id] = variableDefault(variable)
  return out
}

/** Merge user-entered values over the declared defaults, keyed by variable id. */
export function resolveVariableValues(
  variables: PatternVariable[],
  values: PatternVariableValues,
): PatternVariableValues {
  const out: PatternVariableValues = {}
  for (const variable of variables) {
    const provided = values[variable.id]
    out[variable.id] =
      provided !== undefined && provided !== null ? provided : variableDefault(variable)
  }
  return out
}

/**
 * Validate values against variable metadata (§6). Returns a map of variable id →
 * error message; an empty map means the form is valid.
 */
export function validateVariableValues(
  variables: PatternVariable[],
  values: PatternVariableValues,
): Record<string, string> {
  const errors: Record<string, string> = {}
  for (const variable of variables) {
    const raw = values[variable.id]
    const label = variable.label || variable.id

    if (variable.required && isEmpty(raw, variable.type)) {
      errors[variable.id] = `${label} is required`
      continue
    }
    if (variable.type === 'number' && !isEmpty(raw, variable.type) && Number.isNaN(Number(raw))) {
      errors[variable.id] = `${label} must be a number`
    }
  }
  return errors
}

function isEmpty(value: unknown, type: PatternVariable['type']): boolean {
  if (type === 'toggle') return false // a toggle is always either on or off
  return value === undefined || value === null || value === ''
}
