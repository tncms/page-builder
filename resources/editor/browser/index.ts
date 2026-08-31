// PB-TEMPLATE-REGISTRY-2-B/-2-C — public surface of the unified Resource Browser.
export { default as ResourceBrowser } from './ResourceBrowser.vue'
export { default as ResourceCard } from './ResourceCard.vue'
export { default as ResourceDetailPane } from './ResourceDetailPane.vue'
export { default as TemplateConfirmDialog } from './TemplateConfirmDialog.vue'
export { useResourceBrowserStore } from './resourceBrowserStore'
export type { ResourceCatalogClient } from './resourceBrowserStore'
export { useResourceBrowserLauncher } from './useResourceBrowserLauncher'
export { createResourceMaterializer } from './resourceMaterializer'
export type { EditorMaterializationBridge, TemplateImportClient } from './resourceMaterializer'
export type {
  ActionError,
  AvailabilityFilter,
  FilterOption,
  PatternInsertOutcome,
  ResourceBrowserItem,
  ResourceBrowserStrings,
  ResourceCatalog,
  ResourceCatalogBundle,
  ResourceKind,
  ResourceMaterializer,
  TemplateApplyOutcome,
} from './types'
