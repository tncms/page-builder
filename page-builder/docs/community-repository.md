# Community Repository Guide (Phase 26.50A)

> **Status: Foundation over the FROZEN platform.** This phase adds the Community
> Repository *foundation* — how resources are represented, stored, identified,
> distributed, validated and synchronized-ready. It implements **no Marketplace, no
> commerce, no payments, no publishing, no ratings, no sync, no user accounts**
> (Rule 18). The platform is **extended, never modified**.

## 1. Community is another Provider

The Community Repository is **not** a new repository. It is a
`CommunityRepositoryProvider` built on the frozen Provider SDK
([provider-sdk.md](provider-sdk.md)) that contributes resources to the ONE
`DesignResourceRepository`. Marketplace, PRO, Enterprise and Private repositories
are the same shape: another Provider over the same manifest.

```
Builder → Capture → Repository → Manifest → Community Provider → Registry → Library
                                     ▲
                Marketplace / PRO / Enterprise Provider plugs in HERE
                (reuses the identical Manifest — no platform changes)
```

## 2. What Community owns vs. reuses

| Concern | Owner |
|---------|-------|
| Resource representation | **Resource Manifest** (this phase) — [resource-manifest.md](resource-manifest.md) |
| Storage | `CommunityResourceStore` — local `manifest.json` + `payload.json` (+ optional `preview.json`) per resource |
| Identity / aggregation / conflict | the frozen Repository (unchanged) |
| Palette projection | the frozen Registry (unchanged) |
| Preview | the one Runtime Preview route, keyed by RID (unchanged) |
| Compliance | the SDK `ProviderComplianceValidator` (unchanged) |
| Persistence of new content | the Capture Pipeline only (unchanged) |

The provider stores **Manifest + Payload + Preview + Metadata and nothing else** —
never a Builder runtime (Rule 4).

## 3. The provider surface (Rule 11)

`CommunityRepositoryProvider` exposes the uniform surface — no provider-specific
APIs:

- **list** — `all()` (SDK)
- **find** — `find(rid)` (SDK)
- **search** — the repository's `query()` / `ResourceQuery::search()`
- **preview** — Runtime Preview keyed by RID (every resource is RID-resolvable)
- **metadata** — `DesignResource::toMetadata()`
- **manifest** — `manifest(rid)`: the canonical accessor every manifest-backed
  provider shares

It is registered through the SDK's compliance gate in
`PageBuilderServiceProvider` (`registerIfCompliant`). An empty store contributes
nothing, so behaviour is unchanged until Community resources exist.

## 4. Storage layout (offline-ready — Rule 14)

```
<community_path>/
  hero-modern/
    manifest.json     # the Resource Manifest (metadata)
    payload.json      # the id-free renderable LayoutPayload
    preview.json      # optional static preview metadata
```

Default path: `storage/app/page-builder/design-library/community` (override with
`page-builder.design_library.community_path`). This is exactly the layout a Portable
Package, Offline Bundle or Enterprise Mirror places on disk — no changes needed to
consume them. It is **storage, not distribution**: no network, download, sync or
publishing.

## 5. Future commercial compatibility (Rule 12)

A commercial provider (Marketplace/PRO/Enterprise) **reuses the identical manifest**
and only *extends* it with license/purchase/subscription data — it never forks the
manifest, the repository, the registry or the validator. Community stays the
canonical foundation for all of them.

See also: [resource-manifest.md](resource-manifest.md),
[manifest-validator.md](manifest-validator.md),
[compatibility-spec.md](compatibility-spec.md),
[dependency-spec.md](dependency-spec.md).
