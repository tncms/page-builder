# Design Enterprise Distribution (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines enterprise/internal distribution (Step 14): an internal repository, approved-only
> installation, version pinning, policy enforcement, and offline deployment. Enterprise is a
> **policy profile** over the same channels/format — no new runtime, no new package format.
>
> Builds on: [design-distribution-model.md](design-distribution-model.md),
> [design-publication-pipeline.md](design-publication-pipeline.md),
> [design-installation-lifecycle.md](design-installation-lifecycle.md),
> [design-update-lifecycle.md](design-update-lifecycle.md),
> [design-validation-pipeline.md](design-validation-pipeline.md),
> [design-audit-traceability.md](design-audit-traceability.md).
>
> **Conventions:** RFC 2119 keywords. "Org" = an enterprise tenant/administrative scope.

---

## 1. Scope & invariants

- Enterprise distribution is a **policy layer**. It reuses Package v2, the standard
  publication/validation/install pipelines, and the `EntitlementResolver`. It MUST NOT change
  runtime, serialization, resource identity, or the package format.
- All resources remain identical (C1–C7) to any other channel; enterprise controls **which**
  resources are allowed and **who** may install/update — via C8/policy, never by forking
  resources.
- Enterprise MUST support fully **offline** operation (§5).

---

## 2. Internal repository

An org-controlled channel (`enterprise` policy over a `remote`/`imported` transport) that hosts
approved resources/packages.

```ts
interface InternalRepository {
  id: string                 // org repo KEY
  ownerOrg: string           // organization ownerId (distribution-model §1)
  trust: 'org-signed'        // org signing key policy
  approvalRequired: true     // enterprise MUST review+approve before listing
  visibility: 'org' | 'team' // discovery scope within the org
}
```

- Publishing into the internal repo runs the standard publication pipeline with **required**
  review + approval + org signing (publication-pipeline §4, enterprise column).
- The repo is the **authoritative source** for org installs; end users in the org install from
  it, not from public channels (unless policy allows).

---

## 3. Approved-only installation

- An org MAY enforce an **allowlist**: only resources present+approved in the internal repo may
  be installed. Installs of non-approved RIDs are blocked with a `reason`
  `policy.not_approved`.
- Optionally a **denylist/quarantine**: specific RIDs/versions are blocked even if otherwise
  available (e.g. a suspended/compromised pack).
- Enforcement is at **install/update** (the standard enforcement points), consistent with the
  licensing boundary — org policy is an additional gate alongside entitlement/compat.
- Approved-only enforcement MUST be deterministic and audited (who approved, when-as-input).

---

## 4. Version pinning & policy enforcement

### 4.1 Pinning
- An org MAY **pin** RIDs to exact versions or ranges (a policy lockfile):

```ts
interface VersionPin { rid: string; range: string /* semver range or exact */ ; mandatory: boolean }
```

- Pinned installs/updates MUST satisfy the pin; a version outside the pin is blocked
  (`reason` `policy.version_pinned`). `mandatory` pins MAY force a specific version on managed
  installs.
- Pins make org deployments **reproducible**: the same policy + repo → the same installed set.

### 4.2 Policy enforcement points
| Policy | Enforced at | Effect |
|---|---|---|
| Allowlist/denylist | install/update | Block non-approved / quarantined |
| Version pin | install/update | Constrain to pinned range/version |
| Signing required | validation V2 | Reject unsigned/untrusted |
| Auto-update mode | update | Org sets manual/auto/security policy centrally |
| Separation of duties | publication | author ≠ reviewer ≠ approver (required) |
| Entitlement source | import/install | Org-provided entitlement resolver data |

- Policies are **admin-controlled**, versioned, and audited. A policy change MUST NOT
  retroactively break already-inserted content (grandfathering), though it MAY quarantine
  future installs/updates.

---

## 5. Offline deployment

- The internal repo and installs MUST work in **air-gapped** environments: packages are
  imported from local media via the standard offline/`imported` path.
- Offline packages carry everything needed (Package v2 bundles assets/fonts/icons/tokens/
  locales); rehosting resolves to org-internal URLs. No public/cloud dependency.
- Signature verification uses org keys distributed out-of-band. Marketplace-owned fields
  (`verified`/`ratings`) are advisory offline (marketplace-metadata §5.1).
- Determinism: offline import yields the same result as online import of the same package
  (import-export §A).

---

## 6. Multi-tenant / managed installs

- An org may manage many builder installs. A **managed install** applies the org policy set
  (allowlist, pins, signing, update mode) centrally.
- Rollout: policy + pinned versions define the desired state; the installer converges each
  managed install to it via atomic install/update/rollback operations (installation §2), all
  audited.
- Divergence (an install off-policy) is detectable and remediable by re-applying policy;
  remediation never touches inserted content.

---

## 7. Determinism, security, audit

- Given the same repo state + policy set, the approved/pinned install outcome is reproducible.
- All packages pass the full validation pipeline; org signing is verified (validation §2).
- Every publish, approval, policy change, pin, install/update/rollback, and quarantine is
  audited with actor + org scope per
  [design-audit-traceability.md](design-audit-traceability.md). Enterprise typically requires
  the **strictest** audit retention.

---

## 8. Conformance checklist

1. Enterprise is a policy profile over the standard format/pipelines; no runtime/format change (§1).
2. Internal repo requires review+approval+org signing; is the authoritative org source (§2).
3. Approved-only (allow/deny) enforced deterministically at install/update; audited (§3).
4. Version pinning constrains installs/updates; makes deployments reproducible (§4).
5. Fully offline/air-gapped capable; offline == online determinism; org signing out-of-band (§5).
6. Managed installs converge to policy atomically; policy changes grandfather inserted content;
   everything audited (§6–§7).

---

*End of design-enterprise-distribution.md*
