// Phase 26.19 — Template Management Foundation barrel.
//
// Manage registered templates (search, category filter, rename, duplicate, delete) THROUGH
// the TemplateProvider only. No Template Builder, picker, preview, marketplace, persistence,
// or API. See docs/template-management.md.

export * from './templateActions'
export { useTemplateManagementStore } from './templateManagementStore'
