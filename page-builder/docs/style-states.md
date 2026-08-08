# Page Builder — Style States

*Phase 26.10. A state-aware style-resolution layer for interactive components.*

Style states let a component carry different property values for **hover / active /
focus / disabled** on top of its **normal** value. This phase builds the *foundation*
only — a contract, a resolver, a capability model, and a storage seam. There is **no UI
state editor, no renderer change, no CSS state engine, and no animation system** yet; a
future phase adds the editor on the clean seam described below.

The layer is purely additive. A component with no state styles resolves **byte-identically
to before** — `normal` is literally the Phase 26.8 resolver.

---

## 1. Where it sits

State is an *optional layer* on top of the existing chain — it does not replace any
stage:

```
Component State           ← Phase 26.10 (this doc)
      ↓
Component Style   ┐
Page Style        │ ← Phase 26.8 Global Style resolver
Global Style      ┘
      ↓
Token Default     ← Phase 26.7 tokens, after 26.6 responsive collapse
```

Every tier still finalizes through the **responsive engine (26.6)** then **token
resolution (26.7)** via 26.8's `finalizeStyleValue`, so state values compose with
breakpoints and `{{group.name}}` tokens for free.

Module: `resources/editor/components/inspector/styleStates/`

| File | Responsibility |
|---|---|
| `types.ts` | the contract — `StyleState`, `StyleStateValue`, `StyleStateMap`, sources |
| `capabilities.ts` | which component types opt into states |
| `stateRegistry.ts` | the "State + Component Style" tier (reuses 26.8 `GlobalStyleRegistry`) |
| `resolver.ts` | `resolveStatefulStyle` — the deterministic priority chain |
| `stateStyles.ts` | pure read/immutable-set storage helpers (the future-UI seam) |
| `index.ts` | barrel |

---

## 2. The contract

```ts
type StyleState = 'normal' | 'hover' | 'active' | 'focus' | 'disabled'

interface StyleStateValue {           // one state's property bag
  state: StyleState
  properties: Record<string, unknown> // literal | '{{token}}' | responsive envelope
}

// Optional storage that lives ALONGSIDE a component's normal data (never inside a
// property value). Absent/empty = no state styles (the backward-compatible default).
type StyleStateMap = Partial<Record<StyleState, Record<string, unknown>>>
```

The model is extendable: add an id to `StyleState` / `STYLE_STATES` and it flows through
the capability list, the registry, and the resolver unchanged.

### Compatibility

Existing component data is untouched:

```json
{ "color": "#333333" }
```

still works — it is the `normal` value, resolved through the unchanged chain. State
styles, when a future editor adds them, are stored separately (e.g. a sibling
`StyleStateMap`), so there is **no migration**.

---

## 3. Resolution priority

`resolveStatefulStyle(query, options)` — for a **non-normal** state:

```
1. State + Component Override   query.stateOverride (the component's own value for this state)
2. State + Component Style      state-scoped component rule
3. State + Page Style           state-scoped page rule
4. State + Global Style         state-scoped global rule
5. Normal chain                 26.8: Property > Component > Page > Global > Token Default
```

The rule of thumb: **any state-specific value beats any normal value**, and a state with
nothing set **degrades cleanly to the normal result** (so hover looks like normal until
overridden). For `state: 'normal'` (or omitted) the function *is* the 26.8 resolver.

The result reports its winning `source`, one of:
`state-property | state-component | state-page | state-global` (state tiers) then the
26.8 `property | component | page | global | default`.

```ts
resolveStatefulStyle(
  { component: 'button', componentId: 'n1', property: 'color', override: '#111',
    state: 'hover', stateOverride: '{{color.primary}}' },
  { tokens, breakpoint: 'desktop' },
)
// → { value: '#2563eb', source: 'state-property' }
```

`options` are the 26.8 finalize options (`tokens`, `breakpoint`) plus `registry` and
`stateRegistry` (both default to the app-wide singletons).

---

## 4. Capability model

States are for interactive elements — **not enabled blindly**.

```ts
supportsStates('button')  // true  (the one real interactive block today)
supportsStates('text')    // false
statesFor('button')       // ['normal','hover','active','focus','disabled']
statesFor('text')         // ['normal']

registerStateCapableType('link-widget')  // opt in future interactive components
```

A non-capable component only ever sees `normal`, so it behaves exactly as it does today.

---

## 5. State-scoped rules

The "State + Component Style" tier is a `StateStyleRegistry`: one 26.8
`GlobalStyleRegistry` per state, so state rules match with identical specificity. It does
**not** change `GlobalStyleRule` or the global registry.

```ts
stateStyleRegistry.register({
  id: 'btn-hover', state: 'hover', scope: 'global',
  selector: 'button', properties: { color: '{{color.primary}}' },
})
```

---

## 6. The storage seam (future UI)

Two pure helpers are all a future state editor needs; the resolver itself stays
storage-agnostic (the caller extracts the value, exactly like 26.8/26.9):

```ts
readStateProperty(states, 'hover', 'color')            // current value | undefined
const next = setStateProperty(states, 'hover', 'color', '{{color.primary}}')  // NEW map
```

`setStateProperty` is immutable and self-collapsing: clearing the last override drops the
state bag, so the shape returns to empty with no orphan keys.

### Undo / draft flow (unchanged)

```
Edit state style → draft → Apply → update-component-data (one command) → one undo entry
```

The editor commits the new `StyleStateMap` as component data like any other property, so
one edit is one undo entry — the Phase 26 draft/apply/live-preview flow is untouched.

---

## 7. Guarantees

- **No CMS-core changes**, **no renderer changes**, **no CommandBus changes**, **no
  migration**, **no CSS state engine**, **no UI/animation** — all out of scope by design.
- `normal` resolves byte-identically to Phase 26.8 → existing components have zero
  regression (asserted in `__tests__/StyleStates.test.ts`).
