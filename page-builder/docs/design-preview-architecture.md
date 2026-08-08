# Design Preview Architecture (Phase 26.40A)

> **Type:** Product Architecture. **No production code.** Contracts/specification only.
> Part of the Design Library architecture — see
> [design-library-architecture.md](design-library-architecture.md),
> [design-resource-model.md](design-resource-model.md),
> [design-package-specification.md](design-package-specification.md),
> [licensing-boundary.md](licensing-boundary.md),
> [product-decisions.md](product-decisions.md).

Today, preview is **static image/URL only** (Phase 26.36: `TemplatePreview {thumbnail?,
previewUrl?}`, sanitized). This document designs the next tier: a **read-only runtime
preview** that renders a Design Resource through the **real** pipeline so *what you see is
what inserting produces* — without committing anything to the document.

---

## 1. Goals & Principles

1. **Truthful.** Preview must render through the **same** `ComponentInstance` resolve →
   render pipeline as production, so preview output equals insert output.
2. **Read-only / non-committing.** Preview produces **no command, no undo entry, no dirty
   flag, no BuilderState mutation.** It is a *projection*, not an edit.
3. **Fault-isolated.** Runtime preview is an enhancement over the static thumbnail. If it is
   unavailable or errors, the library falls back to the static image and **never blocks
   browsing**.
4. **Cheap by default.** The list shows images; runtime render happens **on demand**
   (on open / hover-intent) and is cached.
5. **Frozen runtime respected.** No new command, no BuilderState change, no serialization
   change.

---

## 2. Layering — the `DesignPreview` model

`DesignPreview` extends today's static preview additively:

```ts
// Illustrative — specification only.
interface DesignPreview {
  thumbnail?: string      // URL only, sanitized — fast default & fallback (26.36)
  previewUrl?: string     // URL only, sanitized — larger static image (26.36)
  runtime?: {             // NEW: optional runtime render capability
    available: boolean    // false → fall back to thumbnail/previewUrl
    kind: 'block' | 'section' | 'page' | 'kit'
    // resolved on demand; never carries an importable definition for gated premium
  }
}
```

Precedence when displaying a preview:

1. **Runtime render** (if `runtime.available` and requested) — the truthful view.
2. **`previewUrl`** static image — if runtime unavailable/not-yet-requested.
3. **`thumbnail`** static image — smallest, always-present fallback.
4. Placeholder — if nothing is available.

The per-kind runtime renderer is provided through the **Public PHP
`PreviewProviderRegistry`** (`register/forType/has/types`) — the natural seam for
kind-specific preview providers, requiring no Frozen-Internal access.

---

## 3. The Five Preview Questions (answered)

### 3.1 Render through the Builder Runtime? — **Yes.**

Preview resolves the resource's **id-free tree** into an **ephemeral preview projection**
and renders it through the real `ComponentInstance` lifecycle (`resolve()` →
`renderView()`/`renderData()`), with the 4 Public runtime hooks firing as normal.

Critically, this projection is a **throwaway**:
- it is **not** applied to `BuilderState`;
- it does **not** dispatch `insert-pattern` or any command;
- it produces **no** undo entry and **no** dirty flag.

It reuses the same resolution the runtime already performs (defaults → responsive → tokens
→ dynamic → conditions), so the preview honors design tokens and responsive settings
exactly as the inserted result would.

### 3.2 Sample content? — **Yes, display-only, discarded.**

Empty or placeholder fields are filled with **sample content** so previews look real
(especially Full Page Templates):

- **Precedence:** resource-provided sample content (from the Package v2 payload / resource
  metadata) → component field **defaults** (`ComponentDefinition::defaults()`) → neutral
  generic placeholder.
- Sample content is **display-only**: it is used to render the preview and then discarded.
  It is **not** what gets inserted unless the resource itself ships that content as its
  real content (Full Page Templates do — see
  [design-resource-model.md §6](design-resource-model.md)).

### 3.3 Desktop / Tablet / Mobile? — **Yes.**

The preview surface offers viewport switching at the builder's **breakpoints** (reuse
`EditorMetadata.breakpoints` / the `TokenRegistry` breakpoints). Because rendering uses the
real responsive resolution, switching viewport width in the preview reflects the true
responsive behavior of the resource.

### 3.4 Same render pipeline as production? — **Yes, server-rendered into a sandboxed iframe.**

**Recommended approach:** render on the **server** through the real
`ComponentRuntime`/Blade pipeline (the same code that renders the published page) and
display the resulting HTML inside a **sandboxed iframe**.

Why server render into an iframe (vs client-side render):
- **Fidelity:** the published site is server-rendered; server-render preview is the *most
  truthful* representation and avoids editor-only client quirks.
- **Isolation:** an iframe scopes the resource's CSS and any theme styles away from the
  editor chrome; the editor's styles don't leak into the preview and vice-versa.
- **Safety:** the iframe is sandboxed; no editor state or privileged context is exposed.

The iframe shows **only the rendered resource** (no editor chrome, no toolbars). Asset and
token CSS load inside the frame as they would on the real page.

### 3.5 Asset preloading? — **Yes, critical-only.**

To avoid layout shift (CLS) in the preview:
- **Preload** the resource's critical assets: hero image(s), the primary font weight, and
  any `previewAssets` declared in Package v2 (see
  [design-package-specification.md](design-package-specification.md)).
- **Lazy-load** below-the-fold media.
- All preview asset URLs remain **URL-only and sanitized** (`isSafeAssetUrl`).

---

## 4. Security

- **URL-only + sanitized.** `thumbnail`/`previewUrl`/preview assets stay URL-only and pass
  `sanitizeThumbnail`/`isSafeAssetUrl` (http/https/relative; reject
  `javascript:`/`data:`/`file:`/`vbscript:`). No storage/attachment ids.
- **No executable code from packages.** Packages never ship scripts; preview renders markup
  + styles only. The runtime render uses only registered components — an unknown component
  type degrades gracefully (compatibility gate), it does not execute foreign code.
- **Sandboxed iframe.** Apply restrictive `sandbox` attributes; disallow top-level
  navigation and script execution beyond what the trusted render pipeline emits.
- **CSP.** Preview output should be served under the same Content-Security-Policy posture as
  the rest of the admin; iframe `src`/content honors CSP (no inline script from resources).

---

## 5. Performance Budget

- **Default = image.** The library list renders `thumbnail` images only — no runtime cost.
- **On-demand runtime render.** Trigger runtime preview on **open** (preview modal) or
  **hover-intent**, not for every list item.
- **Cache.** Cache rendered preview HTML per `(resourceId, viewport)`; previews are
  deterministic for a given resource + breakpoint, so caching is safe and cheap.
- **CWV-style discipline:** preload only the critical font/image; explicit media dimensions
  to prevent CLS; defer non-critical assets. (Aligns with the project's web performance
  targets.)
- **Fallback is instant.** If a runtime render is slow or fails, the static image is already
  on screen; runtime render swaps in when ready and never blocks interaction.

---

## 6. Licensing Interaction

Preview and licensing are deliberately decoupled (full model in
[licensing-boundary.md](licensing-boundary.md)):

- **Premium resources ARE previewable.** They render in the runtime preview and are shown
  with an upgrade/lock **badge** — so users see the value *before* upgrading (the Elementor
  Pro model). Preview is **not** an entitlement gate.
- **Preview ≠ possession.** For a gated premium resource, the preview renders, but the raw
  **importable package/definition/assets stay gated**. The preview surface must never hand
  the client a reusable importable definition for a locked resource — it delivers rendered
  output (and static images), not the source tree.
- **Enforcement is elsewhere.** Entitlement is enforced at **import/install/update**, never
  at discovery or preview.

---

## 7. Fallback & Fault Isolation

| Condition | Behavior |
|---|---|
| `runtime.available === false` | Show `previewUrl`/`thumbnail`. |
| Runtime render throws / times out | Keep the static image on screen; log; do not surface a blocking error. |
| Unknown component in tree | Compatibility gate flags it; preview degrades (skips/placeholder), never executes foreign code. |
| No images at all | Neutral placeholder; library remains fully browsable. |

Runtime preview is strictly **additive**: the static preview path (26.36) remains the
guaranteed baseline.

---

## 8. What This Phase Does NOT Do

- No implementation, no new command, no BuilderState/serialization change.
- No new Frozen-Internal surface; the preview render reuses the existing runtime + the
  Public `PreviewProviderRegistry` seam.
- No caching/CDN infrastructure decisions beyond the budget guidance above.

---

## 9. Summary

| Question | Decision |
|---|---|
| Render through real runtime? | Yes — ephemeral, non-committing projection; no command/undo/dirty |
| Sample content? | Yes — resource → defaults → placeholder; display-only, discarded |
| Desktop/Tablet/Mobile? | Yes — builder breakpoints, real responsive resolution |
| Same pipeline as production? | Yes — server-rendered HTML in a sandboxed iframe |
| Asset preloading? | Yes — critical-only (hero image, primary font, preview assets); lazy below-fold |
| Fallback | Static thumbnail (26.36) — always available; runtime is additive |
| Licensing | Premium previewable + badged; importable source stays gated; enforce at import |

---

*End of design-preview-architecture.md*
