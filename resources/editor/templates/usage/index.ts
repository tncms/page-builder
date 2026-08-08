// Phase 26.32 — Template Pack Usage Workflow Foundation barrel.
//
// A CONNECTING layer between Phase 26.31 adoption discovery and the existing Phase 26.23 apply flow.
// It translates a discovered/adopted template into the apply `TemplateSelection` and hands off to
// the existing confirm → apply → runtime pipeline. It introduces NO new runtime: no
// TemplateExecutionService, no TemplateWorkflowEngine, no `resolveTemplate`, no command, no
// BuilderState mutation, no multi-page wizard. Discovery, the Picker, the runtime, and the Builder
// are unchanged. No bulk generator / marketplace / purchase / licensing. See
// docs/template-pack-usage-workflow.md.

export * from './templateUsageIntent'
export { TemplateUsageManager, type ApplyRequester } from './TemplateUsageManager'
export { useTemplateUsageStore } from './templateUsageStore'
