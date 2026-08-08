// Phase 26.17 — Template runtime barrel: resolver + apply + typed errors.

export { resolveTemplate, type ResolvedTemplate, type ResolveTemplateDeps } from './resolveTemplate'
export {
  applyTemplate,
  applyTemplateById,
  type TemplateApplyTarget,
  type TemplateApplyDeps,
  type TemplateApplyResult,
} from './applyTemplate'
export { TemplateRuntimeError, type TemplateRuntimeErrorCode } from './TemplateRuntimeError'
