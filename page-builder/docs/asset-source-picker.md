# Asset Source Picker (Phase 26.20)

The first slice of Asset/Media integration for the Page Builder Inspector. It gives every
`type: 'media'` field a real picker with two sources — the **CMS Media Library** and an
**external URL** — replacing the manual URL-only input, while storing exactly what the public
renderer already understands: a **plain URL string**.

This phase is the foundation for the 26.20–26.23 series. It is a **URL-based, in-editor
picker + Media Library integration**, not a new media subsystem.

```
type:'media' field → mediaPicker seam → AssetSourcePicker
                                            ├── MediaLibraryBrowser → GET /admin/cms-media-search
                                            └── ExternalUrlInput
                                          returns { src, alt? }  (existing shape)
```

## Why URLs, not ids (audit result)

The Page Builder's **public renderer accepts only a URL string** for image fields
(`data.src`, `data.image_url`), sanitized by the server's `Html::safeUrl` (http/https/relative
only). There is **no server-side id→URL resolution** in the plugin's render pipeline. Storing a
CMS media **id** would render a broken image. Therefore the picker stores the media asset's
**URL** (language-invariant). The CMS Media Library id is kept on the editor-runtime
`AssetReference` for display/traceability only and is **never serialized**.

This also satisfies the phase locks: no new serialized `media` format, no i18n/localization of
media values, and no invented CMS media / CDN / storage / disk / id scheme.

## Files

```
resources/editor/assets/
  contracts/AssetReference.ts      kind (media|external) + src(URL) + alt + id; factories; toMediaResult
  runtime/resolveAssetPreview.ts   isSafeAssetUrl + pure, non-mutating preview resolution
  mediaLibraryClient.ts            searchMediaLibrary → GET /admin/cms-media-search (injectable)
  assetPickerStore.ts              imperative Promise dialog store; confirm gates unsafe/empty
  components/AssetPreview.vue       safe thumbnail (empty / blocked / broken states)
  components/ExternalUrlInput.vue   http(s)-only URL input
  components/MediaLibraryBrowser.vue grid + loading/error/empty; select → media AssetReference
  components/AssetSourcePicker.vue   dialog; registers into the mediaPicker seam
  index.ts                          barrel
```

Integration edit: `App.vue` mounts `<AssetSourcePicker />` once. **No other existing file, no
PHP, no renderer, and no MediaField change** — the seam did all the work.

## Integration seam

`components/inspector/mediaPicker.ts` (Phase 26.5) exposes
`registerMediaPicker(picker | null)` where `MediaPicker = (req) => Promise<{ src, alt? } | null>`.
`AssetSourcePicker` registers itself on mount and clears on unmount:

```ts
onMounted(() => registerMediaPicker((request) => store.openPicker(request)))
onUnmounted(() => registerMediaPicker(null))
```

`MediaField` already calls `hasMediaPicker()` / the resolved picker and commits the returned
`{ src, alt? }` through the **normal `update-component-data` command = one undo step**. So media
edits keep riding the identical Apply/undo path — the picker adds no special-casing.

## `AssetReference` (editor-runtime only)

```ts
type AssetSourceKind = 'media' | 'external'
interface AssetReference { kind: AssetSourceKind; src: string; alt?: string; id?: number }
```

- `src` — the resolvable, language-invariant URL (authoritative, always stored).
- `id` — CMS Media Library id, `media` only, **display/traceability, never serialized**.
- `toMediaResult(ref)` → `{ src, alt? }` — drops `kind`/`id`; this is all that reaches the field.

## URL safety (mirrors the renderer)

`isSafeAssetUrl` allows `http`/`https`, protocol-relative `//`, root-relative `/x`, and
schemeless relative paths; it rejects `javascript:`, `data:`, `vbscript:`, `file:`, and any
other scheme — matching what `Html::safeUrl` would drop. `resolveAssetPreview` is **pure and
never mutates its input**, and the store's `confirm()` refuses an empty or unsafe draft, so an
unsafe URL can never be committed or previewed.

## Media Library integration

`searchMediaLibrary()` reuses the existing authenticated admin endpoint
`GET /admin/cms-media-search?q=&limit=` (backed by `MediaItems`/`MediaSearchController`), which
returns image media as `{ data: [{ id, url, name, alt, … }] }`. The endpoint and `fetch` are
injectable, so nothing is hard-wired and the browser component is testable. Loading, error
(with retry), and empty states are handled. Note: the endpoint is images-only and gates on
admin authentication (not the `media.view` permission).

## Integration matrix (Phase 26.20.1)

Every image-capable field audited across Builder / Pattern / Template, and its integration state
after 26.20.1:

| Field | Location | Persisted format | Uses Asset Picker | 26.20.1 action |
| --- | --- | --- | --- | --- |
| `image.src` | ImageBlock | `string` (URL) | ✅ `media` type + seam | verified |
| `logo.src` | LogoBlock | `string` (URL) | ✅ `media` type + seam | verified |
| `image-section.image_url` | ImageSection | `string` (URL) | ✅ (now) | **`UrlField` → `MediaField`** |
| Template `preview` (thumbnail) | TemplateDefinition | `string?` (URL) | ✅ (now) | **thumbnail picker in Save-as-Template** |
| Pattern tree `image.src` / `logo.src` | `serializePattern` | `string`, copied verbatim | n/a (data) | verified: no id, no runtime state |
| logo link `url` | LogoBlock | `string` (URL) | n/a — a link, not media | none |

Fields that are **not** image assets (hero/cta/testimonial text, `icon.name`) are intentionally
untouched — the picker only attaches to real media fields.

## Portability & `src`-only serialization

Media is always stored as a plain, resolvable **URL string** (or `{ src, alt }` when the field
opts into inline alt). A CMS media **id is never serialized** — the editor-runtime
`AssetReference.id` is dropped by `toMediaResult`. This keeps Builder data portable across
installs and safe to import/export as-is:

```json
{ "src": "https://example.com/image.webp", "alt": "Example" }
```

No schema change, no renderer change, no core change. Existing legacy URL-only content keeps
rendering unchanged, and Pattern/Template capture preserve `src` byte-for-byte.

## Template integration

The **Save-as-Template** dialog gained an optional **Thumbnail** field that reuses the *same*
shared Asset Source Picker (via `assetPickerStore.openPicker`). The chosen URL is stored on the
template's existing `preview` field — **URL only, never an id**. Rename (`update`) and duplicate
(deep-clone) preserve `preview` and every inner `image.src`, verified end-to-end through
`resolveTemplate`.

## Pattern integration

`serializePattern` already copies component `data` verbatim, so `image.src` / `logo.src` survive
capture unchanged. Verification confirms the serialized JSON contains **no numeric media id** and
**no runtime picker state** (`kind`/`AssetReference`) — only the `{ src, alt }` content.

## Non-goals (deferred to 26.21+)

Upload, crop, image search, video-provider integrations, background-image media, galleries /
multiple selection, stock-image search, a new media subsystem/API/marketplace, and any change
to the serialized value shape or the public renderer.
