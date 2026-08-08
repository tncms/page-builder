# Page Builder Editor Design System (PB-UI)

> **Scope.** This is a **Page Builder-owned** editor design system — *not* a shared
> TN CMS Core platform. It styles the editor/admin surfaces only (Structure
> Editor + Frontend/Live Builder). It never touches public theme rendering, Core,
> or other plugins. Established by **PB-UI-1**.

## 1. Token categories

One source: [`resources/shared-ui/tokens.css`](../resources/shared-ui/tokens.css),
imported by both editor bundles via `resources/shared-ui/index.css`. Categories:

| Category | Examples |
|----------|----------|
| Color — surface | `--pb-ui-color-background/surface/surface-muted/surface-elevated` |
| Color — border | `--pb-ui-color-border`, `--pb-ui-color-border-strong` |
| Color — text | `--pb-ui-color-text/text-muted/text-disabled` |
| Color — primary | `--pb-ui-color-primary/-hover/-active/on-primary` |
| Color — status | `--pb-ui-color-success/warning/danger/-hover/-soft/info` |
| Color — interaction | `--pb-ui-color-focus-ring/selection/overlay` |
| Spacing | `--pb-ui-space-2 … -40` (px scale in rem) |
| Radius | `--pb-ui-radius-sm/md/lg/pill` |
| Shadow | `--pb-ui-shadow-sm/md/lg/overlay` |
| Typography | `--pb-ui-font-family`, `-font-size-xs…xl`, `-font-weight-*`, `-line-height-*` |
| Motion | `--pb-ui-duration-fast/normal/slow`, `-easing-standard/emphasized` |
| Z-index | `--pb-ui-z-canvas/overlay/toolbar/dropdown/dialog/toast/drag-preview` |
| Layout | `--pb-ui-topbar-height/sidebar-width/inspector-width/panel-padding/control-height/icon-size` |
| Breakpoints | `--pb-ui-bp-desktop/tablet/mobile`, `--pb-ui-hit-area` |

## 2. Naming conventions

- Every token is prefixed **`--pb-ui-`** and declared **only** on the editor roots
  (`.pb-editor-root`, `.pb-frontend-editor-root`) — never bare `:root`.
- Every class is prefixed **`.pb-ui-`** with BEM-ish modifiers (`.pb-ui-btn--primary`).
- No token or class may depend on the public theme, Bootstrap, or Tailwind.

## 3. Primitive catalog

CSS primitives in `resources/shared-ui/primitives.css`; Vue components alongside.

| Primitive | Form | Notes |
|-----------|------|-------|
| Button / IconButton | `PbButton.vue` / `.pb-ui-btn` | two semantic axes: `tone` (neutral/primary/danger) + `emphasis` (solid/ghost/outline), composed (e.g. tone="danger" emphasis="outline"); `iconOnly` requires `label` |
| Field / Label / HelpText / Input | `.pb-ui-field*`, `.pb-ui-input` | `aria-invalid` error styling |
| Tabs (underline) | `PbTabs.vue` / `.pb-ui-tabs` | ARIA tablist, roving tabindex, arrow/Home/End keys. Underline archetype only — boxed and pill/segmented tab controls are a separate primitive (not yet built) |
| Dialog | `PbDialog.vue` / `.pb-ui-dialog*` | `role=dialog` aria-modal, focus trap, Escape, focus restore. Global modal; `z-index` = `--pb-ui-z-dialog` (40) |
| Notice | `PbNotice.vue` / `.pb-ui-notice` | `variant` info/success/warning/danger + `emphasis` subtle (left-accent) / solid (filled banner); danger/warning → `role=alert`, info/success → `role=status` |
| Badge | `PbBadge.vue` / `.pb-ui-badge` | `variant` neutral/primary/success/warning/danger |
| Spinner / Skeleton | `PbSpinner.vue` / `.pb-ui-spinner` / `.pb-ui-skeleton` | Spinner is `role=status` with a label; reduced-motion aware via duration tokens |
| Divider | `.pb-ui-divider` | token-driven hairline |

Primitives are presentational: **no** persistence, owner, locale, or shell logic.

### Component tokens (per-primitive theming)

Each primitive exposes component-scoped custom properties (`--pb-ui-btn-*`,
`--pb-ui-badge-*`, `--pb-ui-notice-*`, `--pb-ui-tab-*`) whose fallbacks mirror the
base look. A shell or a consumer sets these to preserve its own metrics/colours
**without re-implementing the box and without the primitive knowing shell names**.
This is how one implementation serves visually different shells (Structure vs Live)
— differences are expressed through semantic `tone`/`emphasis`/`variant` props and
these tokens, never by forcing one shell's look on another.

### Legacy compatibility themes

Pre-existing consumers that cannot move to the semantic props this phase (test-
locked inspector/navigator selectors, anchor-as-button chrome, stateful controls)
keep their legacy class **layered on `.pb-ui-*`** as a token-only skin, flagged in
CSS as `LEGACY_COMPATIBILITY_THEME`. Such a skin carries **no** structural behavior
— the box, focus ring, and states come from the primitive; the skin only re-tints
component tokens. **Do not add legacy skins to new consumers — use the `Pb*`
components.**

## 4. Accessibility rules

- Visible `:focus-visible` ring (`--pb-ui-color-focus-ring`) on every interactive primitive.
- Defined hover / active / disabled states.
- Icon-only controls require an accessible label.
- Dialogs trap focus, close on Escape, and restore focus to the opener.
- Tabs use roving tabindex + arrow-key navigation.
- Reduced motion: `prefers-reduced-motion` zeroes the duration tokens.

## 5. Icon policy

One icon source only — the existing `resources/frontend-editor/shell/components/iconLibrary.ts`.
Do not add a second icon library. Size icons with `--pb-ui-icon-size`; mark
decorative icons `aria-hidden="true"`; meaningful icons need a label (an
icon-only `PbButton` carries `label`).

## 6. Responsive foundations (defined, not yet implemented — PB-UI-2)

Breakpoint tokens: `desktop` (1200px), `tablet` (900px), `mobile` (600px).
Principles to apply later: minimum hit area `--pb-ui-hit-area` (2.25rem); panels
collapse before the canvas; top bar overflows into a menu; sidebars stack under
the canvas on mobile; honor `prefers-reduced-motion`. **No responsive behavior
ships in PB-UI-1.**

## 7. CSS architecture

Layers (in load order): **tokens → primitives → per-shell component CSS → shell overrides.**
Rules: namespaced `.pb-ui-` classes only; no generic element selectors; no global
reset; no `!important`; no hardcoded color/spacing in primitives (tokens only).

## 8. Usage example

```vue
<script setup lang="ts">
import PbButton from '@shared-ui/PbButton.vue' // or relative path
</script>
<template>
  <PbButton variant="primary" @click="save">Save</PbButton>
</template>
```

```css
.pb-editor-btn--primary { background: var(--pb-ui-color-primary); }
```

## 9. Forbidden patterns

Hardcoded colors/spacing in primitives · new global CSS reset · Bootstrap/Tailwind
utilities · multiple icon libraries · business/owner/locale/persistence logic in
primitives · runtime writes from UI · duplicate dialog/button systems · per-shell
token copies · public-theme CSS leakage. Guarded by
`resources/shared-ui/__tests__/tokens.test.ts`.

## 10. Migration policy

Migrate incrementally, preserving existing visual values. Consolidation means one
shared primitive implementation and one token/CSS authority — **not** one forced
visual configuration. Legitimate per-shell differences are expressed through
semantic `tone`/`emphasis`/`variant` props and component tokens.

**PB-UI-1** migrated the Structure primary top-bar button as the representative
proof. **PB-UI-1A** consolidated the primitive families:

- **Badge / Spinner** — `PbBadge`/`PbSpinner` added; `StatusBadge` now composes
  `PbBadge`; the standalone `.pb-editor-badge` system removed.
- **Notice** — added the `solid` (filled) emphasis; all six error/alert boxes moved
  to `PbNotice` (gaining `role=alert`); the six legacy classes are token-only skins.
- **Button** — one `.pb-ui-btn` implementation. Structure top-bar chrome is fully
  semantic `PbButton`; inspector/navigator + Live chrome keep their legacy classes
  as `LEGACY_COMPATIBILITY_THEME` skins layered on `.pb-ui-btn`. The dead `.pb-fe-btn`
  system was removed.
- **Tabs** — one underline `.pb-ui-tab` implementation; the inspector tab bar is a
  compat skin on it; the dead `.pb-fe-dock__tab` system was removed.

**Deliberate divergences (documented, not forced):** the two dialogs stay on their
own systems — `.pb-inspector-dialog` is an in-panel `absolute` overlay (not a
global modal) locked by tests; `BuilderLocaleSwitchDialog` is a global modal that
must stack above the Live shell (`z-index:50 > --pb-ui-z-dialog:40`). Boxed /
pill / segmented tab-like controls (palette tabs, responsive breakpoint tabs, the
mode/view switches) are a distinct archetype, not the underline `PbTabs`.

**Remaining debt:** legacy ad-hoc tokens (`--pb-accent`, `--pb-surface`, …) and
hardcoded hex across the shell CSS outside the consolidated families; residual
per-context values in the compat skins (e.g. `#cbd5e1`, `#eef2f7`, error-box font
sizes) not yet in the `--pb-ui-*` set; the broader dead `.pb-fe-*` frontend-shell
chrome; a future boxed/segmented tab primitive; `--pb-ui-z-dialog` vs the shell
stacking contract.
