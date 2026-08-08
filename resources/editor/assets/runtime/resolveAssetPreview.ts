// Phase 26.20 — asset preview resolution + URL safety (pure, non-mutating).
//
// Turns any stored media value (a URL string, a `{ src, alt }` object, or an editor-runtime
// `AssetReference`) into a safe descriptor the preview UI can render. It NEVER mutates its
// input and NEVER resolves ids to URLs (the public renderer only accepts URL strings, so the
// picker works in URLs end-to-end).
//
// URL safety mirrors the server's `Html::safeUrl` scheme policy for images: only http/https
// (and schemeless relative / root-relative / protocol-relative) URLs are allowed. Unsafe
// schemes (javascript:, data:, vbscript:, file:) are rejected so a blocked value can never be
// previewed — matching what the renderer would drop.

import { isAssetReference, type AssetSourceKind } from '../contracts/AssetReference'

/** A resolved, render-safe preview descriptor. */
export interface AssetPreview {
  /** Safe URL to render, or '' when the value is empty or unsafe. */
  src: string
  alt: string
  /** True when there is no source value at all. */
  empty: boolean
  /** False only when a non-empty URL fails the scheme policy. */
  safe: boolean
  /** Echoed from an AssetReference input, for display only. */
  kind?: AssetSourceKind
}

const UNSAFE_SCHEME = /^(?:javascript|data|vbscript|file):/i
const SCHEME = /^([a-z][a-z0-9+.-]*):/i

/**
 * Whether a URL is safe to use as an image source. Allows http/https and any schemeless value
 * (relative, root-relative `/x`, protocol-relative `//host/x`, anchors, queries). Rejects an
 * empty string and any other scheme.
 */
export function isSafeAssetUrl(url: string): boolean {
  const v = (url ?? '').trim()
  if (!v) return false
  if (UNSAFE_SCHEME.test(v)) return false
  const scheme = SCHEME.exec(v)
  if (scheme) return /^https?$/i.test(scheme[1])
  return true // schemeless → treated as a relative/absolute path
}

/** Read a value into a plain { src, alt, kind } without mutating it. */
function readValue(value: unknown): { src: string; alt: string; kind?: AssetSourceKind } {
  if (isAssetReference(value)) {
    return { src: value.src ?? '', alt: value.alt ?? '', kind: value.kind }
  }
  if (typeof value === 'string') {
    return { src: value, alt: '' }
  }
  if (value && typeof value === 'object') {
    const o = value as Record<string, unknown>
    return {
      src: typeof o.src === 'string' ? o.src : '',
      alt: typeof o.alt === 'string' ? o.alt : '',
    }
  }
  return { src: '', alt: '' }
}

/** Resolve any stored media value into a safe preview descriptor. Pure. */
export function resolveAssetPreview(value: unknown): AssetPreview {
  const { src, alt, kind } = readValue(value)
  const trimmed = src.trim()
  if (!trimmed) return { src: '', alt, empty: true, safe: true, kind }
  if (!isSafeAssetUrl(trimmed)) return { src: '', alt, empty: false, safe: false, kind }
  return { src: trimmed, alt, empty: false, safe: true, kind }
}
