# Design Resource State Machine (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the **transition rules** over the states in
> [design-resource-lifecycle.md](design-resource-lifecycle.md) (Step 2): which transitions
> are allowed, which are forbidden, guards, rollback, and recovery. Every transition is
> enumerated.
>
> **Conventions:** RFC 2119 keywords. A transition is written `from ─event─▶ to [guard]`.
> Transitions not listed as allowed are **forbidden** and MUST be rejected.

---

## 1. Alphabet

**States** (authoring/distribution scope, from lifecycle §3):
`draft, validated, packaged, signed, published, installed, active, deprecated, archived`.

**Events** (verbs that drive transitions):
`edit, validate, package, sign, submit, approve, publish, install, activate, deactivate,
deprecate, archive, unpublish, republish, remove, rollback, repair`.

**Guards** (predicates that MUST hold for a transition):
`G-VALID` (validation PASS, no blocking reasons), `G-DET` (deterministic package + checksums),
`G-SIGN` (signature present OR channel allows unsigned), `G-APPROVED` (publication approval
granted), `G-ENTITLED` (EntitlementResolver = granted), `G-COMPAT` (compatible with builder),
`G-PERM` (actor authorized for the channel/ownership), `G-NODEP` (no unmet required
dependency).

---

## 2. Allowed transitions (authoritative table)

| # | From | Event | To | Guards |
|---|---|---|---|---|
| T1 | `draft` | validate | `validated` | G-VALID |
| T2 | `validated` | edit | `draft` | — |
| T3 | `validated` | package | `packaged` | G-DET |
| T4 | `packaged` | edit | `draft` | — (invalidates package) |
| T5 | `packaged` | sign | `signed` | G-SIGN |
| T6 | `signed` | submit → approve | `signed` | G-APPROVED (review outcome; state unchanged until publish) |
| T7 | `signed` | publish | `published` | G-SIGN ∧ G-APPROVED ∧ G-PERM |
| T8 | `published` | install | `installed` | G-ENTITLED ∧ G-COMPAT ∧ G-NODEP (consumer side) |
| T9 | `installed` | activate | `active` | G-ENTITLED ∧ G-COMPAT |
| T10 | `active` | deactivate | `installed`* | ¬G-ENTITLED ∨ ¬G-COMPAT |
| T11 | `published` | deprecate | `deprecated` | G-PERM |
| T12 | `deprecated` | archive | `archived` | G-PERM (sunset elapsed) |
| T13 | `published` | unpublish | `signed` | G-PERM |
| T14 | `deprecated` | republish | `published` | G-PERM (un-deprecate) |
| T15 | `archived` | republish | `published` | G-PERM **only via a new version** (§5) |
| T16 | `installed`/`active` | remove | *(consumer removed)* | G-PERM (consumer) |
| T17 | any packaging state | rollback | prior stable state | §4 |
| T18 | `installed`(broken) | repair | `installed`/`active` | §6, G-VALID ∧ G-COMPAT |

\* T10 lands in the installation scope's `inactive` sub-state (see
[design-installation-lifecycle.md](design-installation-lifecycle.md)); shown as `installed`
here for the authoring-scope view.

---

## 3. Forbidden transitions (non-exhaustive, normative examples)

The following MUST be rejected with a blocking `reason`:

| Forbidden | Why | Reason code (KEY) |
|---|---|---|
| `draft ─publish─▶ published` | Cannot skip validation/packaging/signing/approval | `transition.skip_pipeline` |
| `validated ─sign─▶ signed` | Must be `packaged` before signing | `transition.not_packaged` |
| `published ─install─▶ installed` without G-ENTITLED | Entitlement enforced at install | `entitlement.required` |
| `published ─install─▶ installed` without G-COMPAT | Incompatible builder | `version.builder_out_of_range` |
| `archived ─publish─▶ published` (same version) | Archived artifact is terminal for that version | `transition.archived_terminal` |
| Any transition without G-PERM on a channel that requires it | Unauthorized actor | `permission.denied` |
| `active` while entitlement expired | Backstop enforcement | `entitlement.expired` |
| Renaming RID as a transition | Rename = new resource | `identity.rename_forbidden` |

Rejection is **non-mutating**: a forbidden event leaves the resource in its current state and
emits an audit record of the denied attempt.

---

## 4. Rollback

Rollback returns a resource (or a consumer's installed copy) to a **prior stable state**
atomically. Because packaged artifacts are byte-stable and content-addressed, rollback is
deterministic.

- **Authoring rollback:** `packaged/signed → draft/validated` (T4/T17) discards the package
  but preserves the last validated content. No partial artifacts remain.
- **Publish rollback:** `published → signed` (T13, unpublish) removes the version from the
  channel; already-installed consumers are unaffected (grandfathering).
- **Install rollback:** a failed install commit leaves the consumer library byte-identical to
  its pre-install state (import-export §A.11); the state never reaches `installed`.
- **Update rollback:** downgrading to the previously installed version — see
  [design-update-lifecycle.md](design-update-lifecycle.md); the prior version's package is
  retained (content-addressed) to make rollback possible.

Rollback MUST be idempotent and MUST record an audit entry
([design-audit-traceability.md](design-audit-traceability.md)).

---

## 5. Republish after archive (version rule)

`archived → published` (T15) is permitted **only** by publishing a **new version** of the
same RID:
- The archived version stays archived (immutable, terminal).
- The new version enters at `draft` and traverses the pipeline; on publish it becomes the
  current `published` version.
This preserves the immutability of released artifacts while allowing a line to be revived.

---

## 6. Recovery (broken installs)

An installed copy can become **broken** (installation scope) due to: a missing/incompatible
dependency, a failed prior update, or a corrupted asset (digest mismatch). Recovery:

1. **Detect** — integrity/dependency/compat re-check flags `broken` with a reason.
2. **Repair (T18)** — re-resolve dependencies, re-verify checksums, re-run compatibility;
   if it now passes (G-VALID ∧ G-COMPAT), return to `installed`/`active`.
3. **Reinstall** — if repair cannot succeed, an atomic reinstall from the retained package
   (same version) or an update to a compatible version.
4. **Quarantine** — if neither works, the copy is marked `broken` and excluded from the
   active library (not deleted), never silently removed; the user is offered remove/replace.

Recovery MUST NOT partially mutate the library; each step is atomic.

---

## 7. Concurrency & determinism

- Transitions on a single resource MUST be serialized (no two conflicting transitions apply
  simultaneously); the state after a sequence of events is a deterministic function of the
  starting state and the ordered events.
- Guards are pure predicates evaluated against a snapshot (entitlement, builder env,
  dependency graph); no wall-clock or randomness participates.

---

## 8. State diagram (summary)

```
        edit                      edit                 edit
   ┌───────────┐            ┌───────────┐         ┌───────────┐
   ▼           │            ▼           │         ▼           │
 draft ─validate─▶ validated ─package─▶ packaged ─sign─▶ signed ─publish─▶ published
                                                                              │  │
                                                            unpublish ◀───────┘  │
                                                                                 ├─deprecate─▶ deprecated ─archive─▶ archived
                                                                                 │                 │                    │
                                                                          install│          republish│         republish│(new version)
                                                                                 ▼                 ▼                    ▼
                                                                            installed ─activate─▶ active
                                                                                 ▲   remove/repair   │ deactivate
                                                                                 └───────────────────┘
```

---

## 9. Conformance checklist

1. Only T1–T18 (and their guards) are accepted; all others rejected non-mutatingly (§2–§3).
2. Guards enforce entitlement/compat/permission/dependency at the correct edges (§1–§2).
3. Rollback returns to a prior stable state atomically and is audited (§4).
4. Archived versions are terminal; revival only via a new version (§5).
5. Broken installs are recoverable via repair/reinstall/quarantine, never silent deletion (§6).
6. Transitions serialized + deterministic; no kind/license-specific transitions (§7, lifecycle §2).

---

*End of design-resource-state-machine.md*
