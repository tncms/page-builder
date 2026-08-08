# Page Builder — Template Pack Usage Workflow

*Phase 26.32. A **connecting** layer between Phase 26.31 adoption **discovery** and the existing
Phase 26.23 **apply** flow. It turns a discovered/adopted template into the apply selection and
hands off to the existing confirm → apply → runtime pipeline. It introduces **no new runtime**: no
`TemplateExecutionService`, no `TemplateWorkflowEngine`, no `resolveTemplate`, no command, no
BuilderState mutation, no multi-page wizard. Discovery, the Picker, the runtime, and the Builder are
unchanged.*

```
Starter Packs ─▶ Installed Discovery (26.31) ─▶ Usage Intent (26.32) ─▶ Apply (26.23) ─▶ Runtime ─▶ Builder
                                                  └─ connects; never executes ─┘
```

The usage layer occupies exactly one gap: discovery could show what a site has adopted, but nothing
turned "I want to use this landing template" into the apply flow. Usage is that hand-off — and only
that. Execution stays where it already lives.

---

## 1. Intent (pure translation)

`toUsageSelection(item)` rebuilds the `TemplateLibraryItem` identity an `AdoptionTemplateItem` was
enriched from and wraps it as the apply `TemplateSelection` (`{ templateId, source, item }`). Pure —
no resolution, no command, no state. Only an **available** template (one that resolves in the
Template Library) can be used; an unavailable reference returns `null` rather than a half-built
selection.

`UsageIntentResult` = `{ ok, reason?, selection? }` where `reason` is `not-found` (no such adopted
template) or `unavailable` (adopted but unresolvable in the library).

---

## 2. Manager (the connector, framework-free)

`TemplateUsageManager(discovery?, requestApply?)` is the single seam:

```ts
use(id): UsageIntentResult   // discovery.find(id) → toUsageSelection → requestApply(selection)
```

`requestApply` is an injected `ApplyRequester` — e.g. the existing apply store's `request`. The
manager applies **nothing**: it looks up, translates, and hands off. On `not-found` / `unavailable`
it returns the reason and never calls the requester. No `apply`, `resolveTemplate`, or `execute`
surface exists on it — by design.

---

## 3. Store (editor-local wiring)

`useTemplateUsageStore.use(id)` wires the manager to the real editor stores: it reads the default
`adoptionDiscovery` provider and hands off to `useTemplateApplyStore().request`, which opens the
**existing** confirm → apply flow. The user still confirms; the runtime still owns resolution, the
single `insert-pattern` command, and the one undo. The usage store records only a thin last-intent
outcome (`lastUsedId`, `reason`, `feedback`) for UX — it applies nothing.

### Example — using a template from an adopted Starter Pack

A site installs a landing-page starter pack (26.30) → its templates become discoverable and adopted
(26.31) → the user picks the landing template in a discovery surface → `useTemplateUsageStore.use()`
opens the apply confirmation → on confirm, the existing runtime applies it (one command, one undo).
Usage did not resolve, apply, or mutate anything — it connected the two ends.

---

## Boundaries

- **Connecting only** — translates + hands off; never resolves, applies, or commands.
- No new runtime — no `TemplateExecutionService` / `TemplateWorkflowEngine`, no `resolveTemplate`,
  no CommandBus, no BuilderState mutation, no multi-page wizard.
- The existing apply flow (26.23) and runtime are the sole executors, unchanged.
- Not in scope: bulk generator, marketplace, purchase, licensing, cloud, remote, analytics.
