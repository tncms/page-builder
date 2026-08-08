// Phase 26.20 — Asset Source Picker barrel.
//
// A URL-based media picker for the Page Builder Inspector: choose from the CMS Media Library
// or paste an external URL, with URL-safety enforcement. It integrates through the existing
// `mediaPicker` seam and stores a plain URL (the value shape and public renderer are
// unchanged). No new media subsystem, storage, CDN, API, or serialized format. See
// docs/asset-source-picker.md.

export * from './contracts/AssetReference'
export * from './runtime/resolveAssetPreview'
export * from './mediaLibraryClient'
export { useAssetPickerStore, type AssetPickerSource } from './assetPickerStore'
export { default as AssetSourcePicker } from './components/AssetSourcePicker.vue'
export { default as AssetPreview } from './components/AssetPreview.vue'
