// Phase 26.34 — Template Library Admin Workspace Foundation barrel.
//
// The FREE v1 admin browse surface over the Phase 26.21 Template Library. It reads only the
// `TemplateLibraryProvider`, presents metadata cards with search/filter + states, and routes "Use
// template" through the Phase 26.32 usage workflow → the Phase 26.23 apply confirmation → the
// runtime. It never applies, resolves, dispatches a command, or touches BuilderState. No
// marketplace / selling / catalogs / premium UI. See docs/template-library-admin-workspace.md.

export * from './workspaceFilters'
export * from './templatePreview'
export { exportWorkspaceTemplates, type WorkspaceExportResult } from './workspaceExport'
export { importWorkspacePackage, type WorkspaceImportOutcome } from './workspaceImport'
export { useTemplateWorkspaceStore } from './templateWorkspaceStore'
export { mountTemplateWorkspace } from './mountTemplateWorkspace'
export { default as TemplateLibraryWorkspace } from './TemplateLibraryWorkspace.vue'
export { default as WorkspaceTemplateCard } from './WorkspaceTemplateCard.vue'
export { default as WorkspaceTransferBar } from './WorkspaceTransferBar.vue'
export { default as TemplatePreviewModal } from './TemplatePreviewModal.vue'
