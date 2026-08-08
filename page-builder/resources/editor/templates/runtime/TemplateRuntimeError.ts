// Phase 26.17 — typed template runtime errors.
//
// Resolution and provider failures are explicit and typed — never swallowed, never a
// silent partial result. A leaf module (imports nothing internal) so provider and runtime
// can both use it without an import cycle.

export type TemplateRuntimeErrorCode =
  | 'template_not_found'
  | 'template_definition_not_found'
  | 'unsupported_template_source'
  | 'empty_layout'
  | 'missing_pattern'
  | 'invalid_tree'
  | 'unsupported_layout_kind'
  | 'duplicate_registration'

export class TemplateRuntimeError extends Error {
  readonly code: TemplateRuntimeErrorCode
  readonly details?: Record<string, unknown>

  constructor(code: TemplateRuntimeErrorCode, message: string, details?: Record<string, unknown>) {
    super(message)
    this.name = 'TemplateRuntimeError'
    this.code = code
    this.details = details
  }
}
