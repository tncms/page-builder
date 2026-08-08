# Page Builder — Inspector & Property System

*Phase 26.4 – 26.9. How component properties are declared, edited, validated, and committed.*

The Inspector is **metadata-driven**: no component has a handwritten panel. A
component declares a schema of fields; the Inspector renders editors, stages edits,
validates them, and commits exactly one command. This document is the contract and
the extension guide.

---

## 1. The property contract

A component's editable properties come from its PHP definition's `schema()`
(`Plugins\PageBuilder\Schema\*`), serialized into the editor metadata. The frontend
mirror is `inspector/schema.ts`:

```
ComponentSchema
└── tabs: SchemaTab[]                 // top-level tabs (Content, Style, …)
    └── fields: SchemaEntry[]         // a field OR a nested group
        ├── SchemaGroup  { type:'group', name, label, fields[] }   // collapsible
        └── SchemaField  { name, type, label, default, required,
                           options, help, responsive, translatable,
                           rules, editor, field? }
```

A `SchemaField` carries:

| key | meaning |
|---|---|
| `name` | data key (path within the component's `data`) |
| `type` | which editor renders it (see the registry below) |
| `label` / `help` | UI copy |
| `default` | value used when building default data |
| `required` | drives validation + the Apply gate |
| `options` | for `select` |
| `editor` | editor-specific metadata (e.g. number `min`/`max`) |
| `responsive` | reserved — future per-breakpoint capability |
| `translatable` | value is stored per-locale |
| `field` | the wrapped base field of a `responsive` field |

**Groups** (`type:'group'`) render as collapsible sections and are handled at the
tab level — they are not editors.

---

## 2. The property lifecycle

Editing is three layered stages (`PropertyInspector.vue`):

```
L1  Live Preview   keystroke → live patch into the iframe   (no command, no state)
L2  Component Draft edits accumulate in draftData; component goes dirty
L3  Apply          Apply / Ctrl+Enter / auto-flush → ONE update-component-data command
                   → CommandBus → Component State → preview refresh
```

- **Undo/Redo** is unchanged: one Apply = one `update-component-data` command = one
  undo entry. Live preview and drafts never touch history.
- **Cancel** discards the draft; **Reset** restores defaults (also staged, also one
  command on Apply).

---

## 3. The Property Editor Registry

`fieldEditorRegistry.ts` maps a field `type` → a Vue editor. It is the single place
editors are declared — `InspectorField` resolves through it, so no per-type list is
duplicated anywhere.

```ts
registerFieldEditor(type, { component, needsField? })
resolveFieldEditor(type)      // → { component, needsField } | null
isFieldEditorRegistered(type)
registeredFieldTypes()
```

- `needsField: true` passes the whole `SchemaField` to the editor as a `field` prop
  (used by `select` for options, `number` for min/max, `responsive` for its inner
  field).
- Built-ins are wired in `registerBuiltinFieldEditors.ts` (imported for its side
  effect). Registered today:

  `text · textarea · toggle · select · number · color · url · media · spacing ·
  typography · border · background · link · responsive`

  A field type with no registered editor renders `UnsupportedField` (a visible
  fallback, never a silent blank).

### Supported field types

| type | value shape | editor capabilities | `needsField` |
|---|---|---|---|
| `text` / `textarea` | string | plain / multiline text | — |
| `toggle` | boolean | switch | — |
| `select` | string | options from `field.options` | ✓ |
| `number` | number | `editor.min` / `editor.max` | ✓ |
| `color` | string (`#rrggbb`, `#rrggbbaa`, or token) | swatch, hex/token, **opacity**, reset, **recent colors** | — |
| `url` | string | soft URL hint | — |
| `link` | `{ url, newTab }` | URL + open-in-new-tab (future: nofollow/sponsored/download) | — |
| `media` | string, or `{ src, alt }` when `editor.alt` | preview, choose/replace (picker seam), remove, alt | ✓ |
| `spacing` | `{ top, right, bottom, left }` | linked/unlinked sides (future: units, responsive) | — |
| `typography` | `{ family, size, weight, lineHeight, letterSpacing, transform, decoration }` | metrics + transform/decoration selects | — |
| `border` | `{ width, style, color, radius }` | width/style/color/radius (future: per-corner) | — |
| `background` | `{ type: 'color', color }` | background color (future: gradient/image/video/pattern) | — |
| `responsive` | wraps a base field | renders the inner editor (future: per-breakpoint) | ✓ |

Composite editors keep their value in one object under the field's `name`, so the
draft/diff/undo path (which treats every `SchemaField` as one leaf) is unchanged — a
whole border or link is one dirty field and one undo entry.

### Advanced editor seams (Phase 26.5)

Two capabilities live outside the components so they stay reusable and testable:

- **Recent colors** — `recentColors.ts` holds a session-shared, de-duplicated,
  capped list. `ColorField` records a color with `pushRecentColor` on commit and
  renders the shared `recentColors` list as clickable swatches. In-memory only (a
  convenience, never stored data).
- **Media picker** — the Vue editor has no built-in bridge to the CMS Media Library,
  so `MediaField` asks the `mediaPicker.ts` seam. A host registers one with
  `registerMediaPicker(fn)`; until then the editor falls back to a manual id/URL
  input. Wiring a real picker later is a one-line change with zero editor rework.

### Responsive: the `responsive` flag vs the legacy `type: 'responsive'`

There are two ways a property becomes per-breakpoint — prefer the flag:

- **`responsive: true` on any field (Phase 26.6, canonical)** — turns *any* registered
  editor into a per-breakpoint control. See §6.
- **`type: 'responsive'` wrapper field (legacy)** — wraps a base field via `field.field`.
  `ResponsiveField.vue` resolves the inner editor from the same registry and binds it to
  the single current value. Kept for backward-compatibility; new schemas should use the
  flag instead of the wrapper.

---

## 4. The validation layer

`fieldValidation.ts` is one pure validator shared by the inline per-field feedback
and the Apply gate:

```ts
validateField(field, value)   // → error message | null
isFieldValid(field, value)    // → boolean
```

Rules today (conservative — a forward-compatible superset of the old required-only
check):

- `required` empty → *"This field is required."* (empty **optional** fields are valid)
- `url` / `link` → permissive URL check (absolute, protocol-relative, relative,
  anchor, `mailto:`/`tel:`)
- `number` → numeric, plus optional `editor.min` / `editor.max`

**Composite fields** carry their meaningful string inside an object, so validation
runs against that inner value via `coreValue(field, value)`: a `link`'s `url`, a
`media`'s `src`. This makes `required` and URL rules behave correctly even though the
wrapper object is never itself "empty". A **responsive** field (§6) is first unwrapped
to its `desktop` base — the always-rendered value — so its rules behave as if the
field were non-responsive.

---

## 6. The responsive property engine (Phase 26.6)

Any field can opt into per-breakpoint editing by declaring `responsive: true`
(PHP: `Field::responsive()`). This is a **shared capability**, not a per-type
component — `ResponsivePropertyField.vue` wraps the field's normal editor (resolved
from the same registry, §3) with a breakpoint tab strip and rewrites its edits into an
envelope. There is no `ResponsiveSpacingField` / `ResponsiveColorField` — one wrapper
serves every editor.

### The value contract (matches the server exactly)

A responsive value is stored as an envelope keyed by breakpoint id:

```json
"padding": { "desktop": "40px", "tablet": "20px", "mobile": "10px" }
```

- **Keys are `desktop` / `tablet` / `mobile`** — never `base`. The PHP runtime
  (`src/Runtime/ResponsiveResolver.php`) treats a value as responsive **iff every key
  is a breakpoint id**, and collapses it to `desktop` at render time; any other value
  (a plain string, or a composite like `{ top, right, … }`) passes through unchanged.
  Using the wrong key would silently break rendering — the frontend model
  (`responsive/responsiveValue.ts`) enforces the same key set.
- **Backward-compatible & lazy.** A field is stored as a **plain** value until a
  non-desktop override is set; clearing the last override collapses it back to plain.
  Editing only the desktop breakpoint never creates an envelope. So existing
  components and un-overridden responsive fields render byte-identically.
- **Authoring cascade.** While editing, an unset `tablet`/`mobile` inherits from
  `desktop` (`resolveEffective`). Only explicit overrides are stored; the server
  currently renders `desktop`, so the cascade is a client-only convenience.

### Pieces

| file | role |
|---|---|
| `responsive/breakpoints.ts` | breakpoint registry (ids, labels, order, cascade). Ids match the server + the shell device switcher |
| `responsive/responsiveValue.ts` | pure model — `isResponsiveEnvelope` / `resolveEffective` / `setBreakpoint` / `clearBreakpoint` / `hasOwnOverride` / `baseValue` |
| `responsive/activeBreakpoint.ts` | the editor-global active breakpoint (shared ref; all responsive controls switch together) |
| `responsive/ResponsivePropertyField.vue` | the shared wrapper: tabs, override dots, per-breakpoint reset, inner editor |

### Lifecycle & undo/redo

The whole envelope is one value under the field's `name`, so it flows through the
existing draft → Apply → command path untouched: **one Apply = one
`update-component-data` = one undo entry** (§2), regardless of how many breakpoints
were edited. Live preview patches carry the *effective* value for the active
breakpoint (the iframe renders one breakpoint at a time); switching breakpoints
repaints the responsive fields. A full device-width preview simulator is out of scope
— the breakpoint ids already line up with the shell's device switcher
(`resources/frontend-editor/shell/device.ts`), so wiring them is a future no-op.

---

## 7. Design tokens (Phase 26.7)

A property can point at a **design token** (a named color / spacing / typography / …)
instead of a hardcoded value, so themes stay consistent and re-skinnable. Tokens are
authored on the PHP side (`src/Design/*`) and shipped to the editor in
`metadata.tokens`; the frontend never hardcodes them.

### The reference contract (matches the server exactly)

A token is referenced by a **string**, never an object:

```json
"color": "{{color.primary}}"
```

- **Canonical form is wrapped** `{{group.name}}`; the bare `group.name` form also
  resolves (kept for compatibility on read). The editor **emits the wrapped form**.
- The PHP `DesignTokenResolver` only resolves *string* values (`src/Design/TokenResolver.php`)
  and passes everything else through unchanged. An object like `{ "token": "color.primary" }`
  would **not** be resolved and would break rendering — so the value is always a string.
- **Unknown key → raw passthrough.** A reference to a token that doesn't exist (or a
  literal that merely looks like `a.b`, e.g. `0.5rem`) renders as itself. The frontend
  `TokenRegistry.resolve` mirrors this, so previewing is always safe.
- **Composes with responsive (§6) for free.** The resolve order is
  `responsive → tokens`, so a reference can live inside a breakpoint as a plain string:

  ```json
  "padding": { "desktop": "{{spacing.lg}}", "mobile": "{{spacing.sm}}" }
  ```

  The envelope collapses to the active breakpoint's string, then the token resolver
  resolves it. No new shape.

### Pieces (`inspector/designTokens/`)

| file | role |
|---|---|
| `tokenTypes.ts` | `DesignToken` model + group display metadata (color/spacing/radius/shadow/typography/container/breakpoint) |
| `tokenReference.ts` | pure `parseTokenReference` / `isTokenReference` / `makeTokenReference` (mirrors the PHP regexes) |
| `TokenRegistry.ts` | flattens `metadata.tokens`; `all` / `byType` / `get` / `has` / `referencedKey` / `resolve` (PHP-parity passthrough) |
| `useTokens.ts` | reactive `TokenRegistry` from the editor store metadata |
| `TokenPicker.vue` | reusable picker foundation — lists tokens (optionally by group), emits a `{{key}}` reference |

### Adoption (future)

This phase ships the infrastructure only — no editor is wired to it yet, so there is
zero inspector regression. An editor adopts tokens by rendering the picker behind a
"use token" affordance and resolving for preview:

```vue
<TokenPicker :type="'color'" :model-value="value" @update="onUpdate" @clear="onClear" @commit="onCommit" />
```

`TokenRegistry.referencedKey(value)` tells whether the current value is a real token
(so the editor can show the picker's selection vs. a literal); `TokenRegistry.resolve(value)`
turns a reference into its concrete value for live preview.

---

## 8. Global styles (Phase 26.8)

A property's effective value can come from a **shared style** instead of being set on
every component. This is a resolver *layer*, not a new value shape or a theme engine —
it sits above tokens (§7) and responsive (§6) and reuses them.

### The priority chain (deterministic)

```
Property Override  >  Component Style  >  Page Style  >  Global Style  >  Token Default
```

- **Property Override** — the component's OWN stored value. If set (`undefined`/`null`
  = unset; an explicit `''` counts as set), it always wins. Existing components are
  therefore never changed by adding global styles — the system is **additive, no
  migration**.
- **Component / Page / Global** — registered `GlobalStyleRule`s. The winning raw value
  is then finalized through **responsive collapse → token resolution** (same order as
  the server), so a global rule may itself use a token or a per-breakpoint envelope:

  ```json
  { "id": "body-text", "scope": "global", "selector": "text",
    "properties": { "color": "{{color.primary}}" } }
  ```

- **Token Default** — the schema/token default, the base of the chain.

### The contract (intentionally tiny)

`GlobalStyleRule { id, scope, selector, properties }`. `selector` is a component **type**
(or `*`) for `global`/`page` rules, and a component **instance id** for `component`
rules — no CSS-selector engine. `properties` maps a property name to a value (literal,
`{{token}}`, or responsive envelope).

### Pieces (`inspector/globalStyles/`)

| file | role |
|---|---|
| `types.ts` | `StyleScope`, `SCOPE_PRIORITY`, `GlobalStyleRule`, `StyleQuery`, `StyleResolution` |
| `registry.ts` | `GlobalStyleRegistry` (+ app-wide singleton): `register` / `override` / `unregister` / `byScope` / `match` (last-registered wins) |
| `resolver.ts` | `resolveStyleValue` (priority pick) + `finalizeStyleValue` (responsive→token) + `resolveStyle` (end to end) |
| `defaults.ts` | `DEFAULT_GLOBAL_STYLES` (empty — no imposed styling) + `createStyleRegistry` |
| `useGlobalStyles.ts` | the inspector **seam** — binds the resolver to the live token registry + active breakpoint |

### Seam, not wired UI

This phase ships the resolver foundation only; no editor, renderer, or command path is
changed, so there is zero regression and the singleton registry starts empty. A future
phase adopts it by calling `useGlobalStyles().resolve({ component, property, override, componentId, default })`
from an inspector field to show an "inherited from Global" value/badge or to preview a
global style. The server still owns how a committed component value renders.

---

## 9. Style binding & component adoption (Phase 26.9)

This phase **wires** tokens (§7) and global styles (§8) into real editors, through one
entry point and one wrapper — so no editor grows its own token/style logic.

### The single resolver entry

```ts
resolveComponentStyleValue({ componentType, componentId, property, localValue }, { tokens, breakpoint })
  // → { value, source }
```

It maps the editor's LOCAL value onto the §8 chain (`localValue` = the override) and
finalizes through responsive collapse (§6) + token resolution (§7). A set local value
always wins → existing components are unaffected (no migration).

### The shared wrapper

`StyleBoundField.vue` wraps the **pure** editors for the adopted set —
**`color`, `spacing`, `typography`** (`styleBinding/adoption.ts`) — adding:

- a **source indicator** badge (Local / Token / Global),
- a **token picker** (§7) behind a "{} Token" button; picking emits the wrapped
  reference `{{group.name}}` as the value, unbinding restores a custom value,
- when bound, a token **chip** with the resolved preview instead of the raw editor.

The editors themselves are unchanged and still independently testable. The wrapper is
store-free: it reads a `StyleContext` **provided by `PropertyInspector`** (live tokens +
active breakpoint + component + resolver). With no context (a component mounted in
isolation) it is a transparent pass-through — which is why every prior test still passes.

### Composition & flow

- **Responsive + tokens**: for a responsive adopted field, `StyleBoundField` renders
  *inside* `ResponsivePropertyField`, so a token binds to the active breakpoint —
  `{ "padding": { "desktop": "{{spacing.lg}}", "mobile": "{{spacing.sm}}" } }` — reusing
  §6 with no new responsive system.
- **Live preview**: `PropertyInspector.previewValue` now resolves token references (and
  the responsive breakpoint) so the iframe shows the real color/spacing, not `{{…}}`.
- **Undo/redo unchanged**: binding a token is just a value edit — one Apply → one
  `update-component-data` → one undo entry. The stored value is the reference string,
  which the server's `DesignTokenResolver` already renders (§7).

### Adopted this phase

`color`, `spacing`, `typography` fields — a deliberately small set. Other fields are
untouched. Wiring global-style **authoring** UI (registering rules) is a later phase;
the resolution path is ready for it.

**Why data never corrupts:** edits live in a staged draft; `PropertyInspector`
gates Apply on `invalidFields` (computed via `validateField`). An invalid draft
cannot be committed, so the last applied (valid) value is preserved until the draft
becomes valid again.

---

## 5. Extension guide

**Add a new field type + editor:**

1. PHP: add a `Field` subclass under `src/Schema/Fields/` whose `type()` returns your
   key, and have a component declare it in `schema()`.
2. Frontend: create `fields/MyField.vue` — props `modelValue` (+ `field` if you need
   schema metadata), emits `update` / `commit` / `revert`.
3. Register it in `registerBuiltinFieldEditors.ts`:
   ```ts
   registerFieldEditor('my-type', { component: MyField, needsField: /* true if you read field */ })
   ```
4. (Optional) extend `fieldValidation.ts` if your type has validity rules. If your
   value is a **composite object** (like `link`/`media`), extend `coreValue` so the
   required/URL checks look at the right inner string.

**Editor contract (every field component):**

- Props: `modelValue` (the current value) and — only when registered with
  `needsField: true` — `field` (the whole `SchemaField`, for schema metadata).
- Emits: `update` (stage a new value → live preview + draft), `commit` (flush on
  blur/Enter), `revert` (Escape). The editor never commits to storage itself; Apply
  in `PropertyInspector` owns that (§2).
- A composite editor emits its **whole object** on every `update` (spread the current
  value, patch one key) so the value stays a single leaf for draft/diff/undo.

Your editor is automatically **responsive-capable**: any field can add
`responsive: true` and the shared wrapper (§6) drives your editor per breakpoint — no
per-type responsive component, and no change to your editor.

That's it — the Inspector renders it wherever a component declares a `my-type` field.
No component-specific panel, no changes to `InspectorField`, the renderer, storage,
or the CommandBus.

**Override a built-in editor** (theme/plugin): call `registerFieldEditor` with an
existing type — the last registration wins (a dev-mode warning flags the override).
