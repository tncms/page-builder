# Resource Lifecycle (Phase 26.50B, Rules 1 & 2)

> **Status: Canonical lifecycle over the FROZEN platform.** This is the ONE lifecycle
> every Community Resource follows — reused identically by Marketplace, Enterprise
> and PRO; only authorization differs (Rule 15).

## 1. States

```
Draft → Validated → Packaged → Published → Deprecated → Archived
                                   │            │           ▲
                                   └────────────┴─ archive ─┘
                                                         restore ↺
```

`ResourceStatus` is a strongly-typed enum with exactly six active cases:

| Status | Meaning | Exposed by provider? |
|--------|---------|----------------------|
| `draft` | local working state | no |
| `validated` | passed validation | no |
| `packaged` | package metadata generated | no |
| `published` | live in the Community Repository | **yes** |
| `deprecated` | superseded; searchable with a warning | **yes** (Rule 9) |
| `archived` | hidden, never deleted, restorable | no (Rule 10) |

Reserved (declared, **not implemented** — Rule 2): `rejected`, `suspended`,
`deleted`, exposed as `ResourceStatus::RESERVED`.

## 2. Transitions

Owned solely by `ResourceLifecycle` (pure logic, no I/O):

| From | Allowed to |
|------|-----------|
| Draft | Validated |
| Validated | Packaged, Draft |
| Packaged | Published, Draft |
| Published | Deprecated, Archived |
| Deprecated | Archived, Published |
| Archived | Published, Deprecated (restore) |

Any other transition throws `PublishingException::illegalTransition`.

## 3. Exposure rule (Rule 13)

The `CommunityRepositoryProvider` exposes **only** `published` and `deprecated`
resources. Drafts/validated/packaged stay local; archived are hidden. A resource
with **no** lifecycle document (an offline bundle / enterprise mirror placed
directly on disk) is `published` by convention.

## 4. Manifest immutability (Rule 14)

A published version's manifest is immutable. Editing requires **Working Copy →
Capture → a new publish** at a **greater** version. Re-drafting the same or a lower
version is refused (`PublishingException::manifestLocked`).

See also: [community-publishing.md](community-publishing.md),
[publishing-workflow.md](publishing-workflow.md),
[deprecation-policy.md](deprecation-policy.md).
