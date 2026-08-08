# Design Preview Specification (Phase 26.40B)

> **Type:** Technical Specification (normative, metadata-level). **No production code.**
> Specifies the **preview metadata** a Design Resource/package carries (Step 9): thumbnails,
> runtime-preview capability, device presets, sample content, preview assets, localization,
> and performance hints. The *engine* (how a preview renders) is architected in
> [design-preview-architecture.md](design-preview-architecture.md) (26.40A); this document
> pins the **data contract** consumed by it.
>
> Related: [design-resource-schema.md §C5](design-resource-schema.md),
> [design-package-manifest.md §8](design-package-manifest.md),
> [design-package-v2.md §4](design-package-v2.md) (asset resolution).
>
> **Conventions:** RFC 2119 keywords. All preview URLs are URL-only and sanitized.

---

## 1. `ResourcePreview` shape

```ts
interface ResourcePreview {
  thumbnail?: string          // URL only, sanitized — fast default & fallback
  previewUrl?: string         // URL only, sanitized — larger static image
  caption?: string            // display; localizable
  runtime?: RuntimePreviewMeta // §3
  devices?: DevicePreset[]     // §4
  sample?: SampleContentRef    // §5
  assets?: string[]            // §6 — preview-asset ids to preload
  aspectRatio?: string         // e.g. "16:9" — layout hint to prevent CLS
}
```

| Field | Req | Loc | Validation |
|---|---|---|---|
| `thumbnail` | O | no | safe URL; recommended for every resource |
| `previewUrl` | O | no | safe URL |
| `caption` | O | yes | ≤ 200 chars |
| `runtime` | O | no | §3 |
| `devices` | O | no | §4 |
| `sample` | O | no | §5 |
| `assets` | O | no | each ∈ package `previewAssets[]` ids |
| `aspectRatio` | O | no | `^\d+:\d+$` |

Absent `preview` = the library shows a neutral placeholder; browsing MUST NOT be blocked.

---

## 2. Static preview (baseline)

- `thumbnail` and `previewUrl` are **URL-only**, pass `isSafeAssetUrl` + `sanitizeThumbnail`
  (http/https/relative; reject `javascript:`/`data:`/`file:`/`vbscript:`).
- Bundled preview images (`preview/…`) are declared in package `previewAssets[]` and rehosted
  to URLs on import (design-package-v2 §4.3); the resource stores only the resulting URL.
- Selection precedence for the static image: `previewUrl` → `thumbnail` → placeholder.
- This baseline is the **guaranteed fallback** for runtime preview (§3).

---

## 3. Runtime preview metadata

```ts
interface RuntimePreviewMeta {
  available: boolean          // false → consumers use static baseline
  render: 'server' | 'client' // recommended: "server" (design-preview-architecture §3.4)
  providerKey?: string        // PreviewProviderRegistry key (per-kind provider)
  backgroundToken?: string    // optional token ref for the preview canvas background
}
```

- `available: false` (or absent `runtime`) MUST make consumers fall back to the static image.
- Runtime preview is a **non-committing projection** through the real render pipeline — no
  command, no undo, no dirty flag, no BuilderState change (architecture §3.1). This document
  does not permit any metadata that would violate that.
- `providerKey` maps to the Public PHP `PreviewProviderRegistry` (`forType`), the seam for
  per-kind preview providers.

---

## 4. Device presets

```ts
interface DevicePreset {
  key: 'desktop' | 'tablet' | 'mobile'   // invariant KEYS (closed set)
  width: number                          // CSS px; MUST align with builder breakpoints
  label?: string                         // display; localizable
  default?: boolean                      // exactly one SHOULD be default (else "desktop")
}
```

- `key` is a closed set; widths MUST align with `TokenRegistry.breakpoints()` /
  `EditorMetadata.breakpoints` (see [design-token-specification.md §5](design-token-specification.md)).
- If `devices` is omitted, consumers use the builder's default breakpoint set.
- Preview MUST render responsive states truthfully at each width (real responsive resolution).

---

## 5. Sample content

Sample content makes previews look real without polluting the inserted result.

```ts
interface SampleContentRef {
  strategy: 'resource' | 'defaults' | 'placeholder'  // precedence source
  path?: string          // package-relative sample data (strategy = "resource")
  digest?: string        // REQUIRED when path present
}
```

Normative precedence when rendering a preview (architecture §3.2):
1. `strategy: "resource"` → resource/package-provided sample data (at `path`).
2. `strategy: "defaults"` → component field `defaults()`.
3. `strategy: "placeholder"` → neutral generic placeholder.

Rules:
- Sample content is **display-only** and MUST be discarded after rendering; it is NOT what
  gets inserted — **except** for Full Page Templates whose shipped content *is* the real
  content (that content is the resource payload, not sample data).
- Sample data MUST be JSON-safe and asset-safe (URL-only).
- Determinism: given the same resource + strategy, sample rendering MUST be reproducible.

---

## 6. Preview assets & preloading

```ts
// package manifest
interface PreviewAsset {
  id: string             // referenced by ResourcePreview.assets[]
  path?: string          // package-relative under preview/ … OR
  url?: string           // safe URL
  type: 'image' | 'video'
  role?: 'thumbnail' | 'hero' | 'device-shot' | 'poster'
  width?: number; height?: number
  digest?: string        // REQUIRED when path present
}
```

- `ResourcePreview.assets[]` lists preview-asset ids the engine SHOULD **preload** (hero
  image, primary font via `fonts[]`) to avoid layout shift; other media lazy-load.
- Explicit `width/height` (or `aspectRatio`) MUST be provided for images used above the fold
  to prevent CLS.
- All preview assets are URL-only after rehosting; storage ids never serialized.

---

## 7. Localization

- `caption` and any device `label` are localizable via the resource/package
  `LocalizationTable` (resource-schema §9). KEYS (`devices[].key`, asset ids) are invariant.
- Preview localization is deterministic; no network lookups.

---

## 8. Performance hints (advisory, but bounded)

- The library list uses `thumbnail` only (no runtime cost).
- Runtime preview is on-demand (open / hover-intent) and cacheable per `(rid, device)`.
- Preload critical assets only; defer below-the-fold; honor CWV-style budgets
  (architecture §5). These are hints; consumers MUST NOT let a slow/failed runtime preview
  block browsing — the static baseline is always shown first.

---

## 9. Licensing interaction (recap)

- Premium resources ARE previewable and are shown badged; the importable
  package/definition/assets stay gated (preview ≠ possession). Enforcement is at
  import/install/update, never at preview. See
  [licensing-boundary.md](licensing-boundary.md) and
  [design-marketplace-metadata.md](design-marketplace-metadata.md).
- The preview metadata MUST NOT embed a full importable payload for a gated resource; it
  carries render/display data only.

---

## 10. Conformance checklist

1. Preview URLs are URL-only + sanitized; bundled preview media rehosted (§2, §6).
2. `runtime.available=false`/absent → static fallback; runtime preview never commits (§3).
3. Device presets use closed KEYS aligned to builder breakpoints (§4).
4. Sample content precedence resource→defaults→placeholder; display-only, deterministic (§5).
5. Critical preview assets preloaded with explicit dimensions; browsing never blocked (§6, §8).
6. Captions/labels localizable; KEYS invariant; no runtime payload leak for gated resources (§7, §9).

---

*End of design-preview-specification.md*
