# Design Token Specification (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Defines how Design Resources reference design tokens (Step 8): the token namespace,
> per-category grammar, fallback rules, theme-compatibility, and mapping onto the existing
> **`TokenRegistry`** (Public API, Phase 26.40). Resources bind tokens via `tokens`
> (see [design-resource-schema.md §6](design-resource-schema.md)); packages ship token sets
> (see [design-package-manifest.md §6](design-package-manifest.md)).
>
> **Conventions:** RFC 2119 keywords. Token KEYS are invariant (never localized/renamed).

---

## 1. Why tokens (and the anti-hardcode rule)

To be portable across themes, a Design Resource MUST prefer **token references** over
hardcoded visual values for themeable properties (color, typography, spacing, radius,
shadow, animation). A resource that hardcodes brand colors/spacing will not adapt to the
host theme and is considered **non-conformant** for those properties.

> **Normative rule (anti-hardcode).** For any property that maps to a known token category
> (§3), a conformant resource SHOULD emit a `TokenBinding` (`{ref, fallback}`) rather than a
> raw literal. The `fallback` carries the literal so rendering still works when the token is
> absent (§4). Raw literals are permitted only for values with no token category (e.g. a
> one-off `z-index`, explicit content text).

---

## 2. Token reference model

### 2.1 Binding (recap)

```ts
interface TokenBinding {
  ref: string        // token KEY, e.g. "color.primary"
  fallback: unknown  // literal used when ref is unresolved in the active theme
  scope?: string     // optional path into the resource tree (see §6)
}
```

### 2.2 KEY grammar

```
ref = <category> "." <name> ( "." <name> )*
category ∈ { color, typography, space, radius, shadow, animation, breakpoint }
name matches ^[a-z0-9]+(-[a-z0-9]+)*$
```

Examples: `color.primary`, `color.surface-2`, `typography.heading-1`, `space.4`,
`radius.md`, `shadow.card`, `animation.fade-in`, `breakpoint.md`.

Token KEYS are **invariant**. Renaming a token is a builder/theme version concern (see
[design-version-compatibility.md](design-version-compatibility.md)), never a per-resource
liberty.

---

## 3. Token categories (normative)

| Category | KEY prefix | Value type of `fallback` | Notes |
|---|---|---|---|
| **Color** | `color.` | color string (hex/rgb/hsl/oklch) or `{light,dark}` pair | Semantic names (`primary`, `surface`, `text`) preferred over literal hues |
| **Typography** | `typography.` | `{ family?, size?, weight?, lineHeight?, letterSpacing?, transform? }` | May reference a font family from the package `fonts[]` |
| **Spacing** | `space.` | length (px/rem/`clamp(...)`) | Scale steps (`space.1..space.n`) |
| **Radius** | `radius.` | length | `radius.none/sm/md/lg/full` conventional |
| **Shadow** | `shadow.` | box-shadow string or layered array | Elevation semantics (`shadow.card`, `shadow.overlay`) |
| **Animation** | `animation.` | `{ duration?, easing?, keyframes? }` | Compositor-friendly only (transform/opacity) |
| **Breakpoint** | `breakpoint.` | length | MUST align with builder breakpoints (§5) |

Producers MAY introduce new token names within a category (consumers accept unknown names →
use `fallback`). Producers MUST NOT introduce new **categories** (closed set; a category
change is a builder-version change).

---

## 4. Fallback & resolution rules (normative)

Resolution of a `TokenBinding` at render/preview time is a **pure, deterministic** function:

```
resolve(ref, activeTheme):
  if activeTheme.tokenRegistry.has(ref):  return activeTheme.tokenRegistry.get(ref)
  else:                                    return binding.fallback
```

Rules:
1. Every `TokenBinding` MUST provide a `fallback`. A binding without a fallback is a
   **blocking** validation error (resource-schema §10).
2. Resolution MUST NOT perform network or async lookups; it reads the in-memory registry.
3. Resolution MUST be deterministic: same `(ref, theme snapshot)` → same value.
4. A missing token MUST degrade to `fallback` (never error, never blank), so a resource
   renders on **any** theme.
5. Color tokens MAY resolve to a `{light,dark}` pair; the active color scheme selects one.
   If the theme provides only a single value, it applies to both schemes.
6. `fallback` values are still subject to the asset/URL safety policy where applicable
   (e.g. a typography family that references a font must resolve to a safe source).

---

## 5. Breakpoints & responsive alignment

- `breakpoint.*` tokens MUST align with the builder's breakpoints, exposed by the
  `TokenRegistry` (`breakpoints()`), and mirrored in editor `EditorMetadata.breakpoints`.
- Responsive property values in resource `data` continue to use the builder's existing
  responsive value structure (unchanged); tokens supply the breakpoint boundaries and
  themeable values, not a new responsive mechanism.
- Preview renders responsive states at Desktop/Tablet/Mobile using these breakpoints
  (see [design-preview-specification.md](design-preview-specification.md)).

---

## 6. Scope & application

- A `TokenBinding.scope` (optional) is a path into the resource tree indicating which
  node/property the token applies to. Absent `scope` = the binding documents a token the
  resource *uses* (its `data` already carries the token ref inline).
- Two application styles are permitted and MUST be supported by consumers:
  1. **Inline:** the resource `data` stores the token ref directly for a property, and the
     renderer resolves it (preferred for per-property values).
  2. **Manifest-level (kits):** a kit ships a **token set** (`tokens/<id>.json`) applied
     through the `TokenRegistry` on kit apply, establishing theme-level values that member
     resources then reference. Kit token application uses the Public `TokenRegistry`
     (`register`/`registerMany`) and MUST NOT touch Frozen-Internal state.

---

## 7. Mapping to `TokenRegistry` (Public API)

The existing `TokenRegistry` is the resolution authority (PHP `src/Design/TokenRegistry.php`;
TS `components/inspector/designTokens/TokenRegistry.ts`). Relevant surface:

| Operation | Meaning for tokens |
|---|---|
| `has(ref)` | Is the token defined in the active theme? |
| `get(ref)` | Resolve the token's value |
| `all()` / `groups()` | Enumerate tokens (for the inspector / kit application) |
| `register()` / `registerMany()` | Apply a kit's token set (theme-level) |
| `breakpoints()` | Breakpoint boundaries (responsive alignment) |
| `defaults()` | Builder default token values |
| `toArray()` | Serialize the token table for editor metadata |

Design Resources reference tokens by KEY; the registry (theme-provided) resolves them.
Resources MUST NOT depend on any particular theme having a specific token — the `fallback`
guarantees rendering.

---

## 8. Theme compatibility (normative)

- A resource is **theme-compatible** with any theme because unresolved tokens fall back.
- A resource is **theme-native** with theme T when all its token refs resolve in T (no
  fallbacks used) — a quality signal the library MAY surface, not a gate.
- Themes MAY add/override tokens; adding tokens can only *improve* native-ness. Removing or
  renaming a token a resource references is handled by fallback (still renders) and flagged
  as a compatibility `warning` when detectable.
- Token category changes (add/remove a category) are **builder-version** events governed by
  [design-version-compatibility.md](design-version-compatibility.md).

---

## 9. Determinism & equality

- Token bindings are part of the resource's canonical serialization (C6) and thus part of
  resource identity/equality (resource-spec §7). Two resources differing only in token
  fallbacks are **different** resources.
- Token resolution during render/preview is pure and MUST NOT affect the stored resource
  (preview is a non-committing projection).

---

## 10. Conformance checklist

1. Themeable properties use `{ref, fallback}` bindings, not hardcoded literals (§1).
2. `ref` uses the closed category set + slug names; KEYS invariant (§2.2, §3).
3. Every binding has a `fallback`; resolution is pure, deterministic, degrade-to-fallback (§4).
4. Breakpoints align with `TokenRegistry.breakpoints()` (§5).
5. Kit token sets apply via the Public `TokenRegistry` only (§6–§7).
6. Resources render on any theme; native-ness is a signal, not a gate (§8).

---

*End of design-token-specification.md*
