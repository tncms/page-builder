// Phase 26.18 — Template Authoring Foundation barrel.
//
// The first authoring capability on top of the Template Runtime: capture an existing editor
// layout (a section/container selection or the whole page) into a `TemplateDefinition` and
// register it through the `TemplateProvider`. Local metadata authoring only — no database,
// API, persistence, picker, management UI, or marketplace. See docs/template-authoring.md.

export * from './templateValidation'
export * from './captureTemplate'
export { useTemplateAuthoringStore, type TemplateCaptureScope } from './templateAuthoringStore'
