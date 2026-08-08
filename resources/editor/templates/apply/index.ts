// Phase 26.23 — Template Apply UX barrel.
//
// Connects a Phase 26.22 Picker selection to the Phase 26.17 runtime through a confirmation flow.
// Apply goes ONLY through the runtime (`applyTemplateById`) — one command, one undo, no direct
// BuilderState writes, no reimplemented insertion. Completes the Template v1 lifecycle
// (Contracts → Runtime → Authoring → Management → Library → Picker → Apply UX). No marketplace.
// See docs/template-apply-ux.md.

export { applyTemplateSelection } from './applyTemplateIntent'
export { useTemplateApplyStore } from './templateApplyStore'
export { default as TemplateApplyDialog } from './TemplateApplyDialog.vue'
export { default as TemplateBrowser } from './TemplateBrowser.vue'
