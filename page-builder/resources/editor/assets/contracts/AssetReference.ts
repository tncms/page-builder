// Phase 26.20 — AssetReference contract (editor-runtime only).
//
// A normalized description of WHERE a selected asset comes from, used inside the Asset Source
// Picker. It is deliberately NOT a new serialized data format: the value written back into
// Builder component data stays the existing MediaField shape (a plain URL string, or
// `{ src, alt }`). `AssetReference` never reaches the server, the renderer, or `data`.
//
// Architecture locks honoured here:
//   • URLs are language-invariant — an asset is identified by its resolvable URL, never a
//     per-locale value. (No content localization is introduced.)
//   • We do NOT invent a CMS media subsystem, CDN, storage disk, or id scheme. When the asset
//     comes from the CMS Media Library we keep its integer id for display/traceability only;
//     the URL is what gets stored (the public renderer resolves only URL strings).

import type { MediaPickerResult } from '../../components/inspector/mediaPicker'

/** Where an asset was chosen from. */
export type AssetSourceKind = 'media' | 'external'

/**
 * A picked asset. `src` is always a resolvable, language-invariant URL. `id` is present only
 * for `kind: 'media'` (the CMS Media Library id) and is informational — it is NOT serialized.
 */
export interface AssetReference {
  kind: AssetSourceKind
  src: string
  alt?: string
  id?: number
}

/** Build a CMS Media Library reference (URL is authoritative; id is display-only). */
export function mediaAsset(src: string, opts: { alt?: string; id?: number } = {}): AssetReference {
  const ref: AssetReference = { kind: 'media', src }
  if (opts.alt) ref.alt = opts.alt
  if (typeof opts.id === 'number') ref.id = opts.id
  return ref
}

/** Build an external-URL reference (no id; the URL is all we have). */
export function externalAsset(src: string, alt?: string): AssetReference {
  const ref: AssetReference = { kind: 'external', src }
  if (alt) ref.alt = alt
  return ref
}

/** Runtime guard for an AssetReference. */
export function isAssetReference(value: unknown): value is AssetReference {
  if (!value || typeof value !== 'object') return false
  const v = value as Record<string, unknown>
  return (v.kind === 'media' || v.kind === 'external') && typeof v.src === 'string'
}

/**
 * Reduce a reference to the value the picker seam returns to `MediaField` — the existing
 * `{ src, alt? }` result. `kind` and `id` are dropped: they are editor-only and must never be
 * serialized into Builder data.
 */
export function toMediaResult(ref: AssetReference): MediaPickerResult {
  const result: MediaPickerResult = { src: ref.src }
  const alt = ref.alt?.trim()
  if (alt) result.alt = alt
  return result
}
