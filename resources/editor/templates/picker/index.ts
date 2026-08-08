// Phase 26.22 — Template Picker Foundation barrel.
//
// The first user-facing browse/search/filter/SELECT surface over the Phase 26.21 Template
// Library. Consumes the `TemplateLibraryProvider` only — no apply, no runtime, no BuilderState,
// no command, no undo. Selection is a metadata handoff for a future Apply UX (Phase 26.23). See
// docs/template-picker-foundation.md.

export * from './templatePickerFilters'
export { useTemplatePickerStore, type TemplateSelection } from './templatePickerStore'
export { default as TemplateCard } from './TemplateCard.vue'
export { default as TemplatePicker } from './TemplatePicker.vue'
