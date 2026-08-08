// Phase 26.21 — Thumbnail asset boundary.
//
// Templates must stay portable: a thumbnail is a URL, never a media/attachment id or storage
// metadata (Phase 26.20.1). This reuses the Phase 26.20 asset URL boundary — http/https and
// relative URLs pass; dangerous schemes (`javascript:`, `data:`, `file:`, `vbscript:`) and blanks
// are dropped rather than stored. Values are URLs by contract; the guard protects the scheme.

import { isSafeAssetUrl } from '../../assets'

/** Return the thumbnail URL only when it is a safe asset URL; otherwise undefined. Pure. */
export function sanitizeThumbnail(value: string | undefined | null): string | undefined {
  if (typeof value !== 'string') return undefined
  const trimmed = value.trim()
  if (!trimmed) return undefined
  return isSafeAssetUrl(trimmed) ? trimmed : undefined
}
