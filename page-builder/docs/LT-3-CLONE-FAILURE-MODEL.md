# LT-3 — Clone Failure Model

**Status: IMPLEMENTED (LT-3).** How the clone service maps every failure to a safe, typed
`LayoutTranslationCloneOutcome` — never a raw exception, SQL, model or row.

## Typed outcomes

| Outcome | Meaning | Source |
|---|---|---|
| `Created` | Target document + binding created atomically; status EXISTS+DRAFT+IN_SYNC. | success |
| `AlreadyCloned` | Target already owns a binding (idempotent repeat / pre-check / write race). | eligibility / write |
| `FeatureDisabled` | `page-builder.translation.enabled` is false — no write. | eligibility |
| `CapabilityUnsupported` | The implementation does not advertise `clone`. | eligibility |
| `SameTranslation` | Source and target are the same page translation. | eligibility |
| `DifferentTranslationGroup` | Source and target are in different groups. | eligibility |
| `TargetNotFound` | Target page translation (ContentTranslation) does not exist. | eligibility / re-resolve |
| `SourceNotFound` | No readable source layout document. | eligibility / re-resolve |
| `SourcePayloadUnavailable` | Source exists but has no valid published/draft document. | eligibility / re-resolve |
| `TargetLayoutConflict` | The target owner already has a layout document. | eligibility / in-tx re-check |
| `InvalidIdentity` | Command identities are not canonical. | eligibility |
| `IntegrityFault` | A referential/persistence integrity fault (FK, unreadable state). | write normalization |
| `ConcurrentConflict` | A concurrent writer won the race (unique violation). | write normalization |
| `PersistenceFailure` | Any other unexpected persistence error. | write normalization (fallback) |

## Normalization rules (service boundary)

- **Eligibility ineligibility** → mapped 1:1 to the outcome above (read-only, no transaction).
- **In-transaction pre-checks** (target already bound / target owner has a layout) → throw an internal
  `CloneConflictException(outcome)` → transaction rolls back (nothing written) → mapped outcome.
- **Binding write result** not successful → `AlreadyExists → AlreadyCloned`, `Conflict →
  ConcurrentConflict`, `InvalidReference/InvalidIdentity → IntegrityFault`, else `PersistenceFailure`
  (the layout write already made in the same transaction is rolled back).
- **`QueryException`** escaping the transaction → message inspected: `unique`/`duplicate` →
  `ConcurrentConflict`; `foreign key` → `IntegrityFault`; else `PersistenceFailure`.
- **Any other `Throwable`** → `PersistenceFailure`.

Exception messages are never surfaced. `Throwable` is never caught-and-pretend-success. The result
carries only the typed outcome, the created binding (on success), and an optional non-sensitive detail.

## Recovery

- A failed clone leaves **no** partial writes (atomic rollback): no orphan target layout, no binding,
  no partial status.
- A retry re-resolves canonical persisted state; database uniqueness is the final guard.
- Integrity faults are observable via diagnostics (`LayoutTranslationBindingValidator`), never
  auto-repaired.
