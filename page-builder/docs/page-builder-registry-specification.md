# Page Builder — Registry Specification

**Phase 26.40 — Extension API Freeze.** This is the frozen behavioral contract for every **Public**
registry in Page Builder FREE: registration timing, ordering, duplicate handling, immutability, and
failure behavior. These guarantees are SemVer-protected (changing one is a MAJOR change) and are
locked by tests.

---

## 1. Registry behavior matrix

| Registry | Order guarantee | Duplicate handling | Reads immutable? | Frozen after boot? | Error on violation |
|----------|-----------------|--------------------|------------------|--------------------|--------------------|
| **PHP** `BlockRegistry` / `SectionRegistry` | ascending call order | **overwrite** (last wins) | arrays (own copy) | no | — |
| **PHP** `RuntimeHookManager` | callbacks fire in registration order per point | additive (many per point) | — | no | — |
| **PHP** `PatternRegistry` (discover) | merged by ascending source priority | higher-priority source **wins** on dup id; invalid skipped → `invalid()` | yes (per-request) | idempotent per request | — |
| **PHP** `PreviewProviderRegistry` | by type | **override by type**; unknown → default | — | no | — |
| **PHP** `PatternCapabilityRegistry` | — | **idempotent** (re-register = no-op) | — | no | — |
| **PHP** `ColumnLayoutPresetRegistry` | merge by ascending provider `priority()`, dedup by id, sort by `order` | higher priority **wins** on dup id | yes (per-request cache) | no | — |
| **PHP** `TokenRegistry` | insertion; grouped view | **override by key** | copies | no | — |
| **TS** `InsertProviderRegistry` | registration order (`keys()`/`all()`) | **throw** | spread copy | no | `InsertRegistryError` |
| **TS** `PatternLibraryRegistry` | Map insertion order | register **overwrites** by id; `duplicate()` → fresh id | spread (not deep) | no | — |
| **TS** `LocalTemplateProvider` | Map insertion order | **throw** (`duplicate_registration`) | deep-cloned (JSON) | no | `TemplateRuntimeError` |
| **TS** `TemplateDistributionRegistry` | fixed order (builtin→local→imported) | **throw** | via library (immutable) | no | `DistributionRegistryError` |
| **TS** `BuiltinTemplatePackRegistry` | registration order | **idempotent skip** by pack id | immutable copies | **yes** (`freeze()`) | `BuiltinPackError` |
| **TS** `TemplateStarterPackRegistry` | registration order | **throw** on dup id | deep-cloned | **yes** (`freeze()`) | `StarterPackError` |
| **TS** `fieldEditorRegistry` | none | **overwrite** (DEV-warns) | Map snapshot | no | — |

**Design note — three intentional duplicate policies.** They are not inconsistent; they encode intent:
- **Throw** (Insert, LocalTemplateProvider, distribution, starter packs) — *identity-strict* surfaces
  where a collision is a bug the author must see.
- **Overwrite** (Block/Section, PatternLibrary, TokenRegistry, PreviewProvider, fieldEditor) —
  *last-write-wins* surfaces where a later plugin/theme intentionally supersedes an earlier default.
- **Idempotent skip** (builtin packs, capabilities) / **priority merge** (patterns, presets) —
  *boot-safe* surfaces that tolerate re-registration and layered sources.

## 2. Determinism guarantees

- Every registry backed by a JS `Map` or an ordered PHP array returns items in **registration order**;
  `all()`/`keys()`/`types()` are deterministic across runs given the same registration sequence.
- Merge-based registries (patterns, presets) are deterministic given the same source set + priorities;
  ties resolve by ascending priority then declared `order`.
- The Insert launcher always presents categories in `INSERT_CATEGORY_ORDER`
  (`blocks → patterns → templates → assets`), independent of registration order.

## 3. Immutability guarantees

- Reads never expose a mutable reference to internal storage that would let a caller corrupt another
  consumer's view:
  - Template providers/packs/starter packs return **deep clones**.
  - Insert/pattern/distribution registries return **shallow copies** of their collection (`[...map.values()]`);
    the *definition objects* are treated as immutable by convention — do not mutate a returned
    `PatternDefinition`/`InsertProvider` in place; use `update()`/`duplicate()`.
- Pure helpers (`resolveAssetPreview`, `isSafeAssetUrl`, `checkPackageCompatibility`,
  `serialize/exportTemplatePackage`) never mutate their inputs.

## 4. Extension lifecycle

```
Register ──▶ Validate ──▶ Discover ──▶ Use ──▶ Dispose (optional)
```

| Stage | Timing | Behavior |
|-------|--------|----------|
| **Register** | Boot (PHP: service-provider `boot()`; TS: `main.ts`/`workspace.ts` once, idempotent) | Add the definition/provider to its registry. Boot registration is guarded by try/catch — a failing extension never blocks the editor from mounting. |
| **Validate** | At registration (packs) or first access (pattern discovery) | Validation-first: packs/packages throw a typed error **before** anything is registered (atomic — no partial state). |
| **Discover** | On demand | Discovery layers (`templateLibrary`, distribution registry, pattern discovery) read registries and return immutable metadata snapshots. They never resolve trees or dispatch commands. |
| **Use** | User action | Applying a template/pattern resolves the definition then routes through the **single** command pipeline (`insert-pattern`) → one undo entry. Runtime hooks fire during component `resolve()`/`render()`. |
| **Dispose** | Optional | Registries expose `clear()`/`flush()`/`forget()`/`remove()` for teardown (used in tests and re-wiring). Runtime hooks: `flush(point?)`. Most FREE registries live for the session/request; there is no per-instance disposal requirement. |

### 4.1 Registration timing rules
- **Register once, at boot.** Do not register inside a component `onMounted`/render — registries are not
  request-scoped caches. TS boot registration calls `clear()` first where idempotency matters
  (`registerBuilderInsertProviders`).
- **Availability:** a registered extension is available to discovery immediately after boot completes.
- **Ordering:** registration order is the tie-breaker everywhere except merge registries (priority) and
  the Insert launcher (fixed category order).
- **Failure behavior:** a throwing extension at boot is caught and isolated — the specific extension is
  absent, the Builder still mounts. A throwing **duplicate** registration on an identity-strict registry
  surfaces the typed error to the caller (it is a programming error, not a runtime condition).

## 5. Failure-behavior summary

| Situation | Result |
|-----------|--------|
| Duplicate key on identity-strict registry | typed `*RegistryError` / `TemplateRuntimeError('duplicate_registration')` thrown to caller |
| Invalid pack/package | typed error (`BuiltinPackError`/`StarterPackError`/`TemplatePackageError`) **before** any registration (atomic) |
| Unknown template source | `TemplateRuntimeError('unsupported_template_source')` |
| Missing template in known source | `TemplateRuntimeError('template_definition_not_found')` |
| Register after `freeze()` | `BuiltinPackError`/`StarterPackError('registry-frozen')` |
| Extension throws at boot | caught + isolated; editor still mounts |

---

Guarantees here are frozen under the
[Versioning & Deprecation Policy](./page-builder-versioning-and-deprecation-policy.md) and enumerated in
the [Contract Inventory](./audits/phase-26.40-extension-api-freeze-audit.md).
