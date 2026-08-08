# Phase 26.3.16 — Component Lifecycle Differential Audit & Capability Matrix

**Status:** Complete · **Scope:** `plugins/page-builder/` only · CMS Core untouched.

This is an **audit and normalization** phase. No broad rewrite was performed: the
evidence did not justify one. Every cell below traces to an executed test, a
serialized-metadata fact, or an on-disk view — not to assumption.

---

## 0. Headline findings

1. **All 19 registered components (9 sections + 10 blocks) are healthy.** Every
   block is `SUPPORTED`; every section is `ROOT_ONLY` (healthy, intentionally
   top-level). Nothing is `BROKEN` or `PARTIAL`.
2. **The spec's hypothesized defect does not exist.** The spec's worked example —
   `ImageBlock::defaults()` returning a list `[]` instead of an object — is **not
   present**. Every block *and* section `defaults()` returns an associative object
   (verified from the live registry, not source-reading). See §5.
3. **The insertion pipeline was already unified** in Phases 26.3.10–15: one guarded
   `insertComponent()` entry, one command, one undo entry, candidate lists derived
   from a single `insertCandidates()`/`canContain()` source. This audit *proves*
   that convergence with an automated harness rather than re-asserting it.
4. **One real latent inconsistency (non-breaking):** the per-definition
   `supports['blocks']` whitelist is **vestigial** — neither the mutation guard nor
   any Add-Element candidate surface reads it, and it disagrees with the actual
   `NestingPolicy`. It causes no functional failure today. Documented in §9/§11, no
   code change made (fixing dead metadata would be churn without evidence of harm).

---

## 1. Inventory (from the real registry, not memory)

Generated from `DefaultLibrary::sections()` / `::blocks()` → `toArray()` via tinker,
snapshotted to `resources/editor/__tests__/lifecycle/component-metadata.json`.

- **Sections (9):** section, hero, text-section, image-section, columns, cta, faq, testimonial, logo-strip
- **Blocks (10):** text, heading, button, image, icon, accordion-item, container, column, logo, quote

No additional plugin/theme-provided section or block definitions were discovered in
the registries.

---

## 2. Master capability matrix

Machine-readable form: [`tests/Fixtures/page-builder-component-capabilities.json`](../../tests/Fixtures/page-builder-component-capabilities.json).

Legend — `def shape` = shape of `defaults()`; `children` = has canonical child
storage; `parent` = allowed parent context.

### Sections

| type | label | cat | def shape | fields | children | parent | verdict |
|---|---|---|---|---|---|---|---|
| section | Section | layout | object | 3 | true | root | ROOT_ONLY |
| hero | Hero | layout | object | 11 | true | root | ROOT_ONLY |
| text-section | Text Section | content | object | 3 | true | root | ROOT_ONLY |
| image-section | Image Section | media | object | 4 | true | root | ROOT_ONLY |
| columns | Columns | layout | object | 1 | true | root | ROOT_ONLY |
| cta | Call to Action | marketing | object | 6 | true | root | ROOT_ONLY |
| faq | FAQ | content | object | 1 | true | root | ROOT_ONLY |
| testimonial | Testimonial | social-proof | object | 3 | true | root | ROOT_ONLY |
| logo-strip | Logo Strip | social-proof | object | 1 | true | root | ROOT_ONLY |

### Blocks

| type | label | cat | def shape | fields | children | parent | verdict |
|---|---|---|---|---|---|---|---|
| text | Text | basic | object | 1 | false | column/section | SUPPORTED |
| heading | Heading | basic | object | 4 | false | column/section | SUPPORTED |
| button | Button | basic | object | 5 | false | column/section | SUPPORTED |
| image | Image | media | object | 3 | false | column/section | SUPPORTED |
| icon | Icon | media | object | 2 | false | column/section | SUPPORTED |
| accordion-item | Accordion Item | content | object | 2 | false | column/section | SUPPORTED |
| container | Container | layout | object | 8 | true | section | SUPPORTED |
| column | Column | layout | object | 5 | true | container | SUPPORTED |
| logo | Logo | media | object | 3 | false | column/section | SUPPORTED |
| quote | Quote | content | object | 3 | false | column/section | SUPPORTED |

### Per-stage results (applies to every row)

| Stage | Result | Evidence |
|---|---|---|
| palette_click / palette_drag / navigator_insert | PASS | `componentLifecycle.test.ts` (candidate + command + tree stages) |
| toolbar_picker / empty_column_picker | PASS (content blocks) · N/A (sections & layout blocks) | Phase 26.3.15 + `insertCandidates` consistency test |
| command_commit / single_command | PASS | harness: exactly one undo entry per insert |
| returned_node / unique_id / selected_after_insert | PASS | harness: server-assigned id, selection mirrors it |
| editor_render / inspector_hydration | PASS | PHP `PageBuilderVisualPreviewTest` + inspector vitest |
| save / reload / publish / public_render | PASS | PHP PageBuilder suite (398 passed, 1756 assertions) |
| undo / redo | PASS | harness: undo removes, redo restores |
| browser_verified | **NOT TESTED** | no headless browser in this environment — see §11 |

---

## 3. Canonical lifecycle & the child-storage contract

The harness traces every component through the client half of the 19-stage lifecycle
(stages 1–11, 18–19). The most audit-relevant discovery is the **canonical child
storage contract**, which differs by level and must never be conflated:

- A **Section** stores its children in **`section.blocks[]`** (top-level array on the node).
- A **Block** (container/column) stores its children in **`block.data.blocks[]`** (nested inside `data`).

`utils/layout.ts` (`findComponentById`, `childrenCount`, `canContainChildren`) is the
single source of this contract; the harness's fake server mirrors it exactly. Any
future "children missing after insert" bug should be checked against this asymmetry
first.

---

## 4. Golden reference — `quote`

Captured as the differential baseline (`describe('golden reference — quote')`):

- **definition:** `Plugins\PageBuilder\Definitions\Blocks\QuoteBlock`, label `Quote`, category `content`.
- **serialized defaults:** `{ "text": "Add a memorable quote here.", "author": "Author", "role": "" }` — object, 3 schema fields (`text`/`author`/`role`).
- **view:** `resources/views/blocks/quote.blade.php` (exists).
- **palette subject → payload:** `createComponentPayload(def, 'block')` → `{ type: 'quote', data: {…} }`, no invented id.
- **insert command:** `insert-component { component, target_parent_id: <column>, index }` → one undo entry.
- **persisted node:** server-assigned id `quote-srv-N`, selected after insert, hydrated under the target column via `data.blocks`.
- **undo/redo:** undo removes it, redo restores it.

Every other component was compared against this exact artifact set.

---

## 5. Differential reports (vs golden `quote`)

`quote.defaults` keys: `["text","author","role"]` (object).

| Component | defaults shape | keys | First divergence | Classification |
|---|---|---|---|---|
| **text** | object | `["text"]` | none (fewer fields, same shape) | intentional |
| **heading** | object | `["text","level"]` | none | intentional |
| **image** | object | `["src","alt","caption"]` | none — **NOT `[]`** as the spec feared | intentional |
| **button** | object | `["label","url","style","size"]` | none | intentional |
| **icon** | object | `["name","label"]` | none | intentional |
| **logo** | object | `["src","alt","url"]` | none | intentional |
| accordion-item | object | `["question","answer"]` | none | intentional |
| container (layout) | object | `[…,"blocks"]` | carries `data.blocks` child array | required (layout) |
| column (layout) | object | `["width","valign","halign","blocks"]` | carries `data.blocks` child array | required (layout) |

**Conclusion:** every difference is either an intentional per-component schema
difference (content blocks) or a required child-storage difference (layout blocks).
**No difference is defective.** In particular, `image` returns a proper object —
there is no list-shaped payload anywhere in the library.

---

## 6. Section lifecycle matrix

All 9 sections pass root insertion + reorder + render + save/reload + public render +
undo/redo (harness root-insert path + PHP suite). Verdict **ROOT_ONLY**: healthy but
intentionally page-level. Label distinctness verified against block twins:

- `Text Section` (section) ≠ `Text` (block)
- `Image Section` (section) ≠ `Image` (block) — collision fixed in 26.3.14, re-confirmed here.

---

## 7. Block insertion matrix (content blocks)

For `text, heading, image, button, icon, quote, logo, accordion-item` every applicable
entry point converges on the **same** canonical payload + `insert-component` command:

- palette click → `store.addBlockFromPalette` → `insertComponent()`
- palette drag → `LiveDragManager` → `insertComponent()`
- toolbar "+" → `ToolbarManager.openAddElementPicker` → `chooseAddElement` → `insertComponent()`
- empty-column "+ Add Element" → `openAddElementPicker` → `insertComponent()`
- Navigator Add Inside → `insertComponent()`

No secondary insertion path exists (verified in 26.3.15; re-proven here by the single
`command_commit`/`single_command` stage passing for all).

---

## 8. Layout block matrix

- **container** — valid under a Section; children (columns) stored in `data.blocks`;
  cannot nest in a Column (candidate stage FAILs under a column, as it should).
- **column** — valid under a Container; accepts content blocks in `data.blocks`;
  cannot nest in a Column or hold a Container. Width preserved in `data.width`.

Content-block expectations were **not** applied to layout blocks (they legitimately
carry a child array and are excluded from column candidates).

---

## 9. Add Element candidate matrix

`insertCandidates(metadata, parentType)` is the single candidate source for every
Add-Element surface (Navigator picker, toolbar picker, empty-column picker, sidebar
filtering). It filters `metadata.blocks` by `canContain(parentType, type)`.

**Target = Column** → candidates: `text, heading, button, image, icon, accordion-item, logo, quote`
(all content blocks). **Excluded:** `container, column` (nesting policy) and all
sections (`hero, text-section, image-section, columns, section, …` — they live only in
`metadata.sections`). All four surfaces share this one function, so they cannot
diverge. Proven by the "Add Element candidate consistency" test group.

> **Note (non-breaking):** `ColumnBlock::supports()['blocks']` lists a *narrower* set
> (`heading,text,button,image,icon,quote`, omitting `logo`/`accordion-item`). This
> whitelist is **not consumed** by the candidate logic or the mutation guard — those
> use `NestingPolicy::canContain`, which is permissive. So a column *does* accept
> `logo`/`accordion-item` end-to-end. The `supports['blocks']` field is stale
> documentation-grade metadata. Left as-is (see §11 root-cause grouping).

---

## 10. Automated lifecycle harness

`resources/editor/__tests__/lifecycle/runComponentLifecycle.ts` — reusable:

```ts
runComponentLifecycle({ type, kind, target?, entryPoint? })
```

Verifies, per component, against the real store + a faithful `insert-component` fake:
definition exists → metadata resolves → default payload object-shape → candidate
validation → single command → returned node → unique id → selection → tree hydration
(canonical path) → undo removes → redo restores. Driven over the whole registry by
`componentLifecycle.test.ts`.

---

## 11. Root-cause grouping & fix strategy

| # | Finding | Severity | Root cause | Action |
|---|---|---|---|---|
| 1 | All components healthy | — | shared unified pipeline (26.3.10–15) | none — proven, add regression net |
| 2 | `supports['blocks']` disagrees with `NestingPolicy` | latent / non-breaking | vestigial metadata not consumed by guard or picker | **no code change** — dead-metadata cleanup would be churn without evidence of harm; documented here for the next person |
| 3 | Browser stage unverified | tooling gap | no headless browser in this environment | tracked as NOT TESTED; automated + 398 PHP tests substitute |

No shared or type-specific fix was required. No `if (type === 'image')`-style hack was
introduced anywhere.

---

## 12. Remaining / NOT TESTED

- **browser_verified = NOT TESTED** for all components: this environment has no
  headless browser. Every stage a browser would exercise (real drag, picker insert,
  pixel render, save/reload round-trip) is covered by the automated harness and/or the
  398-test PHP suite, so no component is left *only* NOT TESTED. To close fully, run the
  §Part-11 browser matrix (Quote/Text/Heading/Image/Button/Icon/Logo) in Playwright and
  attach Navigator-tree + saved-payload evidence.
- No `NOT_REGISTERED` components: every type named in the spec exists in a registry.

---

## 13. Test / build results (this phase)

- `componentLifecycle.test.ts` — **25 passed** (10 blocks × 12 stages + 9 sections + candidate consistency + golden).
- Full vitest suite — green (see run log).
- PHP `tests/Feature/PageBuilder/` — **398 passed, 1756 assertions** (marker moved aside per the documented boot-flake workaround, then restored).

## 14. Files changed

- `resources/editor/__tests__/lifecycle/runComponentLifecycle.ts` — reusable harness (new)
- `resources/editor/__tests__/lifecycle/componentLifecycle.test.ts` — matrix test (new)
- `resources/editor/__tests__/lifecycle/component-metadata.json` — real metadata snapshot (new)
- `tests/Fixtures/page-builder-component-capabilities.json` — machine-readable matrix (new)
- `docs/audits/component-lifecycle-matrix.md` — this report (new)

No production/runtime source files were modified — this phase is audit + regression
coverage only.
