# Design Distribution Model (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines **ownership** (Step 3), **distribution channels** (Step 8), and **marketplace
> operations** (Step 12) for Design Resources/packages. Distribution is a layer over the
> frozen resource/package contracts; it MUST NOT change runtime or resource identity.
>
> Builds on: [design-resource-specification.md](design-resource-specification.md) (RID,
> sources, C8), [design-resource-lifecycle.md](design-resource-lifecycle.md),
> [design-publication-pipeline.md](design-publication-pipeline.md),
> [design-marketplace-metadata.md](design-marketplace-metadata.md),
> [licensing-boundary.md](licensing-boundary.md).
>
> **Conventions:** RFC 2119 keywords. Ownership/channel/operation KEYS are invariant.

---

## 1. Ownership (Step 3)

Ownership answers "who controls this resource's lifecycle and distribution." It is **C8
metadata** and MUST NOT affect runtime or resource equality (marketplace-metadata §1).

```ts
interface Ownership {
  owner: 'first-party' | 'user' | 'community' | 'vendor' | 'organization'
  ownerId: string        // invariant KEY (stable owner handle)
  transferable: boolean  // may ownership move? (default: false for first-party/community)
  maintainers?: string[] // additional actor ids permitted to drive lifecycle
}
```

| Owner type | Who | Typical channel | Transfer |
|---|---|---|---|
| `first-party` | The product vendor | builtin / official | No |
| `user` | An end user (their saved resources) | user / local | To org (enterprise) |
| `community` | A community contributor/maintainer | community / remote | Maintainer handoff |
| `vendor` | A commercial seller | marketplace / premium | Seller-to-seller (marketplace policy) |
| `organization` | An enterprise tenant | enterprise/internal repo | Within org |

### 1.1 Ownership transfer (normative)
- Transfer changes `ownerId`/`maintainers` only; it MUST NOT change the **RID**, the payload
  (C1–C7), or any inserted content.
- Transfer requires `G-PERM` from the current owner (and marketplace/org policy where
  applicable) and is **audited** (from→to owner).
- Transfer is only allowed when `transferable = true`; first-party/community defaults are
  non-transferable unless policy overrides.

---

## 2. Distribution channels (Step 8)

A **channel** is a source/policy pair that publishes and serves resources. Channels reuse the
invariant `source` KEYS; they differ in trust, entitlement, and publication policy — **not** in
format (one Package v2 everywhere).

| Channel (KEY) | source | Trust/signing | Entitlement | Publication policy |
|---|---|---|---|---|
| `builtin` | builtin | vendor-signed | free/bundled | first-party |
| `official` | builtin/official | vendor-signed | free/bundled | first-party review+approval |
| `community` | community (remote) | advisory (self/maintainer) | typically free | maintainer review |
| `marketplace` | marketplace | seller-signed (required-configurable) | pricing/trial | marketplace review+approval |
| `premium` | premium | signed | premium | as marketplace/official |
| `imported`/offline | imported | may be unsigned (advisory) | resolver-checked | local (self) |
| `cloud` | cloud (reserved) | provider policy | provider-supplied | provider |
| `enterprise`/internal | enterprise (reserved/remote) | org-signed | org policy | internal review+approval |

Normative:
- **One package format across all channels.** A channel MUST NOT require a bespoke format.
- Discovery across channels **badges** entitlement and **never hides** premium
  (licensing-boundary §4).
- `marketplace`, `remote`, `cloud` remain **reserved** transports; `community`/`premium`/
  `enterprise` are **policy facets** mapping onto them. No new runtime is introduced by any
  channel.
- A resource MAY be distributed via multiple channels; its **RID and payload are identical**
  across channels; only C8 (entitlement/marketplace/ownership) differs.

---

## 3. Marketplace operations (Step 12)

Operations a marketplace/channel performs on a published listing. Each is **atomic**,
**permission-gated** (`G-PERM`), and **audited**. None alter the resource payload (C1–C7) or
inserted content.

| Operation (KEY) | Effect | Lifecycle mapping | Consumer effect |
|---|---|---|---|
| `publish` | Make a version discoverable | → `published` (T7) | Installable |
| `hide` | Remove from search/listing, keep installable by direct link | `published` (visibility=`unlisted`) | Not surfaced; still installable if referenced |
| `unpublish` | Withdraw from channel | `published → signed` (T13) | No new installs; existing grandfathered |
| `suspend` | Emergency takedown (policy/legal/security) | `published → signed`/blocked, flagged | No new installs; existing MAY be flagged; content grandfathered |
| `replace` | Point listing at a successor RID | deprecate old + `supersededBy` | Migration suggested (opt-in) |
| `feature` | Editorial promotion | display metadata only | Surfaced prominently; no runtime effect |
| `verify` | Grant verified-vendor/pack badge | marketplace-owned flag (authoritative) | Trust badge shown |
| `deprecate` | Mark obsolete | `published → deprecated` (T11) | Discouraged; update suggested |
| `archive`/`sunset` | Retire after window | `deprecated → archived` (T12) | No new installs; grandfathered |
| `transfer` | Change ownership | §1.1 | None to payload/content |

Normative rules:
- **Grandfathering is absolute:** `unpublish`/`suspend`/`deprecate`/`archive` MUST NOT break
  already-installed/active copies or inserted content.
- `verify`/`feature`/`hide` are **display/discovery** operations; they MUST NOT touch payload
  or entitlement enforcement.
- `suspend` is the only expedited/emergency op; it MAY be policy-forced but still cannot revoke
  inserted content (copy-on-insert), though enterprise policy MAY additionally quarantine
  installs (enterprise doc).
- Marketplace-owned fields (`verified`, `ratings`, `downloads`) are authoritative only from the
  marketplace; offline copies show them as advisory (marketplace-metadata §5.1).

---

## 4. Cross-channel identity & coexistence

- The same RID published to multiple channels is the **same resource**; consumers resolving it
  see identical C1–C7. Entitlement/marketplace metadata (C8) is channel-specific.
- Version coexistence: multiple published versions of a RID may exist across/within channels;
  consumers resolve by range/pin (update-lifecycle §6).
- Conflicts between channels (same RID+version, different digest) are a **conflict** at install
  (installation §2.6) and MUST be resolved deterministically by policy, never silently.

---

## 5. Determinism & audit

- Channel resolution (which version/source a consumer gets) is deterministic given the resolved
  library + policy inputs; the core resolver performs no remote fetch (remote/cloud channels
  are reserved seams).
- Every ownership transfer and marketplace operation is audited (actor, op, from→to, timestamp
  as input) per [design-audit-traceability.md](design-audit-traceability.md).

---

## 6. Conformance checklist

1. Ownership is C8 metadata; transfer changes owner only, never RID/payload/content; audited (§1).
2. One package format across all channels; discovery badges (never hides) premium (§2).
3. `marketplace`/`remote`/`cloud` remain reserved transports; channels add policy, not runtime (§2).
4. Marketplace operations atomic, permission-gated, audited; display ops never touch payload (§3).
5. Grandfathering absolute across unpublish/suspend/deprecate/archive (§3).
6. Cross-channel RIDs identical in C1–C7; conflicts resolved deterministically (§4).

---

*End of design-distribution-model.md*
