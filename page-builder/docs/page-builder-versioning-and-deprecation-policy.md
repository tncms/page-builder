# Page Builder — Versioning & Deprecation Policy

**Phase 26.40 — Extension API Freeze.** This policy governs the **public extension surface** of Page
Builder FREE (the contracts classified **Public** in the
[Extension API Audit](./audits/phase-26.40-extension-api-freeze-audit.md)). It is the compatibility
contract between the Builder core, third-party extensions, and future Page Builder PRO.

Scope note: **Internal** and **Experimental** APIs are *not* covered by these guarantees and may change
at any time. Only **Public / frozen** APIs are protected.

---

## 1. Semantic Versioning

The Page Builder extension API follows [SemVer 2.0.0](https://semver.org): `MAJOR.MINOR.PATCH`.

| Bump | Meaning for the frozen extension API | Examples |
|------|--------------------------------------|----------|
| **PATCH** (`x.y.Z`) | Bug fixes, docs, internal refactors. **No** change to any Public contract, signature, or documented behavior. | Fix a registry ordering bug that violated the documented guarantee; clarify a JSDoc. |
| **MINOR** (`x.Y.0`) | **Additive, backward-compatible** growth of the Public surface. Existing extensions keep working untouched. | Add a new Insert category key; add a new optional field to a DTO; add a new provider method with a default; register a new built-in pack. |
| **MAJOR** (`X.0.0`) | Any **breaking** change to a frozen Public contract. | Remove/rename a Public method or interface; make an optional field required; change a return type; change a documented registry guarantee (order/duplicate). |

**Breaking a frozen Public API requires a MAJOR version.** No exceptions.

### What counts as backward-compatible (MINOR)
- Adding a new optional property to a DTO / interface.
- Adding a new method **with a default implementation** to an abstract base class.
- Adding a new registry, provider, category key, or event.
- Widening an accepted input (e.g. accepting an extra union member).
- Relaxing a validation that previously rejected input.

### What counts as breaking (MAJOR)
- Removing or renaming any Public export, method, field, event, or category key.
- Adding a **required** parameter or making an optional field required.
- Narrowing a return type or an accepted input type.
- Changing a documented registry guarantee (registration order, duplicate handling, immutability).
- Changing the shape of an event payload or a serialized DTO consumed by extensions.
- Moving a Public export to a different module path.

### Reserved (Experimental) keys are exempt
Reserved-but-inert source/category keys (`marketplace`, `remote`, `cloud`) are **Experimental**.
Activating one is **MINOR** (additive). They carry no stability guarantee until promoted to Public.

---

## 2. Deprecation Policy

Frozen Public APIs are **never removed without a deprecation cycle**. Removal only happens in a MAJOR
release, and only after the API has been deprecated for the full support period.

### 2.1 Workflow

```
Mark deprecated ──▶ Ship replacement ──▶ Support period ──▶ Remove (MAJOR only)
   (MINOR)            (same release)      (≥ 1 MINOR, ≥ 6 mo)    (next MAJOR)
```

1. **Mark** — annotate the API `@deprecated` with the version deprecated, the reason, and the
   replacement. Add it to the "Deprecations" table in the Extension Guide.
2. **Replace** — ship the replacement API in the **same** release, so no window exists where an author
   has no supported path.
3. **Warn** — emit a `import.meta.env.DEV`-guarded console warning (TS) / a logged notice (PHP) the
   first time a deprecated API is used. Never break at runtime.
4. **Support** — keep the deprecated API fully functional for the support period below.
5. **Remove** — only in the next MAJOR, and only if the support period has elapsed.

### 2.2 Support period

| Tier | Minimum support after deprecation |
|------|-----------------------------------|
| Public / frozen | **≥ 1 MINOR release AND ≥ 6 months**, removed no earlier than the next MAJOR |
| Experimental | none (may change without a cycle) |

### 2.3 Migration guidance

Every deprecation ships with:
- The exact replacement API and a **before/after** code snippet.
- A one-line rationale.
- The target removal version (next MAJOR).

Migration notes live in the Extension Guide's "Deprecations" section and in release notes.

### 2.4 Do-not-remove rule

Frozen Public APIs **must not be removed** outside this workflow. A frozen contract that turns out to
be a mistake is deprecated and superseded — not deleted — until a MAJOR boundary.

---

## 3. Frozen-core boundary

The runtime internals — **CommandBus**, **BuilderState**, the component **serialization** format, and
Undo/Redo — are **FROZEN-INTERNAL**: not part of the extension API, not directly callable by
extensions, and changed only through the single command pipeline. Extensions interact with them **only**
via the Public contracts (component definitions, providers, registries, runtime hooks). This policy
does not grant extensions access to those internals.

---

## 4. Change checklist (for maintainers)

Before merging a change that touches `resources/editor/{insert,patterns,templates,assets}` or
`src/{Contracts,Definitions,Registries,Runtime,Patterns,Presets}`:

- [ ] Does it change a **Public** contract? → determine PATCH / MINOR / MAJOR per §1.
- [ ] If breaking → is it gated behind a MAJOR release?
- [ ] If removing → has it completed the §2 deprecation cycle?
- [ ] Do the frozen-contract tests still pass (registry determinism, duplicate rejection)?
- [ ] Are runtime / serialization / BuilderState / CommandBus untouched?
- [ ] Is the Extension Guide + Contract Inventory updated?
