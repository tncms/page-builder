# LT-3 — Transaction Boundary Governance (LOCK)

**Status: RATIFIED & LOCKED (LT-3).** Governs the atomic write boundary of every Layout Translation
write workflow.

## 4.1 Transaction owner

The **application-level clone service** owns the transaction boundary:

```
LayoutTranslationCloneService  (owns transaction)
    → create target pb_layouts document
    → create translation binding + initial status
    → commit
```

Repositories **participate** in the transaction; they do **not** orchestrate the use case.

## 4.2 Repository responsibility

A repository may insert/update its own aggregate, enforce local invariants, and return typed results.
A repository must **not** begin/commit the complete workflow, call another repository to implement the
business workflow, clone layouts, resolve eligibility, calculate capability, synchronize, or swallow a
failure to preserve a partial write.

## 4.3 Atomicity

One atomic transaction covers: (1) target layout document creation, (2) target binding creation, (3)
initial three-dimensional persisted status. **All commit or none commit.** Forbidden post-failure
states: target layout without binding; binding without target layout; binding with incomplete status;
partially copied payload.

## 4.4 Transaction infrastructure

Use the framework/database transaction abstraction **at the application-service boundary**. Domain value
objects (descriptor, identity, status, capability, validator, diagnostics) are **never** coupled to
Laravel DB APIs. The service may depend on a small `TransactionRunnerInterface` for testability. **No**
generalized Unit-of-Work framework is introduced (the project has none).

## 4.5 Nested transactions

Correctness must **not** rely on nested-transaction/savepoint behaviour (not identical across SQLite and
MySQL). Repositories called by the clone service operate **within** the service's already-open
transaction. If any existing repository method opens its own transaction, it is audited and the composed
workflow is proven safe on both engines; the actual behaviour is documented. **Audit result:** the LT-2
`EloquentLayoutTranslationBindingRepository` and `LayoutStore` do **not** open their own transactions —
their writes are single statements that compose safely inside the service transaction.

## 4.6 External side effects

**No irreversible external side effects inside the transaction**: no notifications, webhooks, queue
jobs, analytics, or remote API calls. Any domain event is dispatched **only after a successful commit**
via an existing after-commit mechanism. **No outbox** is built in LT-3.

## 4.7 Retry safety

A rollback followed by a retry is safe. The workflow holds **no** in-memory state from a prior attempt;
every retry **re-resolves canonical persisted state**. Database uniqueness (LT-2
`UNIQUE(page_translation_key)` / `UNIQUE(layout_key)`) remains the final concurrency guard, normalized
to a typed already-exists/conflict outcome at the service boundary.
