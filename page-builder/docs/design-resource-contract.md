# Design Resource Contract (Phase 26.49A)

> **Status: FROZEN — authoritative architecture specification.**
> This is the official contract every current and future provider (Built-in,
> Imported, My Designs, Community, Marketplace, PRO, Portable Package) must obey.
> After this phase the platform is **extended, never modified**. Future phases must
> reference this document.

This phase introduces **no end-user functionality**. It formalizes and freezes the
contracts that the Design Resource platform already implements.

---

## 1. The platform at a glance

```
Builder
  ↓  (Save as Design)
Capture Pipeline        — the ONLY content-persistence path
  ↓
Repository              — READ-oriented single source of truth
  ↓
Registry               — presentation-oriented palette
  ↓
Library                — discovery / browsing (incl. My Designs management)
  ↓  (Open in Builder)
Working Copy           — isolated draft, never the repository payload
  ↓  (Save as Design)
Capture Pipeline → new Resource
```

The lifecycle is a **cycle through capture**, never `Repository → Builder →
Repository`. See [`../architecture/design-resource-platform.md`](../architecture/design-resource-platform.md).

---

## 2. Resource Identity — RID (Rule 2)

`RID = <source>:<kind>:<resourceId>` is the **Resource Identity**.

| Property        | Guarantee |
|-----------------|-----------|
| globally unique | yes       |
| immutable       | yes       |
| never regenerated | yes     |
| never reused    | yes       |
| never editable  | yes       |

**RID identifies the resource, not its content.** Two resources with different
content share nothing; two resources with identical content still have distinct
RIDs.

Formal type: `Plugins\PageBuilder\Repository\Rid` and the accessor
`DesignResourceContract::rid()`.

---

## 3. Content Identity — Fingerprint (Rule 3)

The **Fingerprint** is the **Content Identity**.

| Property        | Guarantee |
|-----------------|-----------|
| deterministic   | yes       |
| immutable       | yes       |
| canonical       | yes (canonical-JSON of the normalized payload) |
| payload-only    | yes       |

The fingerprint **never** depends on metadata, author, preview, timestamps or
runtime ids. **Duplicate resources may share the same fingerprint** — that is how a
duplicate is recognised as "the same design, different identity".

Computed by `Plugins\PageBuilder\Capture\DesignFingerprint::of()` from the
normalized payload only.

---

## 4. Payload Contract (Rule 4)

The normalized Builder payload is **immutable**. It is never modified after
capture. A repository payload may only ever be *replaced* through a future **Update
Existing** workflow (not implemented; reserved). No management operation, no
metadata edit, and no working-copy edit ever mutates a repository payload.

---

## 5. Metadata Contract (Rule 5)

Metadata is the **only** mutable surface. Editable fields:

- name
- description
- category
- tags
- visibility
- version notes

Everything else (RID, fingerprint, payload, compatibility, preview, author,
timestamps) is **system-managed**. See
[`design-resource-management.md`](design-resource-management.md) for the editing
lifecycle (Phase 26.49).

---

## 6. The contracts (formal types)

| Concern    | Official contract (`Plugins\PageBuilder\Contracts\…`) | Backing type |
|------------|-------------------------------------------------------|--------------|
| Resource   | `DesignResourceContract`   | `Repository\DesignResource` |
| Provider   | `DesignResourceProvider` (extends `Repository\Contracts\DesignResourceProviderInterface`) | 5 resource providers |
| Repository | `RepositoryContract` (extends `Repository\Contracts\DesignResourceRepositoryInterface`) | `Repository\DesignResourceRepository` |
| Registry   | `RegistryContract`         | `Builder\Registry\BuilderResourceRegistry` |

The two pre-existing interfaces (provider, repository) are **reused**, not
duplicated: the official `Contracts\*` names extend them without adding methods.

Detailed per-contract docs:
[repository](repository-contract.md) ·
[registry](registry-contract.md) ·
[capture](capture-pipeline-contract.md) ·
[working copy](working-copy-contract.md) ·
[provider development guide](provider-development-guide.md).

---

## 7. Preview Contract (Rule 11)

There is exactly **ONE** preview implementation: **Runtime Preview** (`Preview/`
engine + `page-builder/preview` route). No screenshot engine, thumbnail generator
or "Preview Renderer v2" may be introduced. Future caching layers must **wrap**
Runtime Preview, never replace it.

---

## 8. Authorization Contract (Rule 13)

Ownership verification is **always** server-side. The Builder never trusts
client-claimed RID, metadata or provider ownership. Providers implement
authorization through the same contract (see the provider development guide).

---

## 9. Event Contract (Rule 14) — frozen, additive-only

`DesignCaptured` · `DesignMetadataUpdated` · `DesignArchived` · `DesignRestored` ·
`DesignDeleted` · `DesignDuplicated`.

Future events must be **additive only** — never rename or repurpose an existing
event.

---

## 10. Versioning Contract (Rule 15)

The platform supports **Resource Version metadata only**. History, revision tree,
rollback, merge and Update Existing are **not** implemented (reserved).

---

## 11. Forbidden changes (Rule 17)

Explicitly forbidden, now and forever under this contract:

- Direct payload / fingerprint / RID mutation
- Repository / Registry / Working-Copy bypass
- Preview replacement
- A second Capture Pipeline, Repository, Registry or Runtime Preview

---

## 12. Architecture lock (Rule 20)

This is the **official architectural freeze point**. Community, Marketplace, PRO
and Portable Package must **extend** these contracts. They must not redesign the
platform.
