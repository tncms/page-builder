# Design Package Sharing (Phase 26.47)

Portable, local export/import of Design Resources through the Design Library. A
user exports a design to a single `.tncms-design` file, shares that file, and
imports it into another TN CMS installation — where it becomes an ordinary
`imported` Design Resource, previewable and usable through the existing library
workflow.

This is **transport only**: no marketplace, no licensing enforcement, no cloud, no
network, no asset downloader.

## Architecture

```
DesignResource ──export──▶ .tncms-design file ──import──▶ ImportedPackageStore
                                                                │
                                                                ▼
                                              ImportedResourceProvider (source=imported)
                                                                │
                                                                ▼
                                                    DesignResourceRepository ──▶ Library / Preview / Full Page Import
```

- **Exporter** (`Package\DesignPackageExporter`) resolves a resource through the
  repository, validates the portable-package boundary, strips runtime node IDs, and
  emits a canonical, deterministic package.
- **Importer** (`Package\DesignPackageImporter`) validates an uploaded package and,
  when clean, persists it via the store.
- **Store** (`Package\ImportedPackageStore`) is the only persistence — one
  `.tncms-design` JSON file per package under a configurable local directory.
- **Provider** (`Repository\Providers\ImportedResourceProvider`) reads the store and
  serves each resource with `source = imported`. Consumers still go through the
  repository (Rule 1); the runtime is unchanged (Rule 2).

## Package format (FREE v1 single-file profile of Design Package v2)

A `.tncms-design` file is one canonical JSON document:

```json
{
  "schemaVersion": 2,
  "type": "design-package",
  "generator": "tncms-page-builder",
  "metadata": { "id": "agency-home", "name": "Agency Homepage", "source": "builtin", "createdAt": null },
  "resources": [
    { "kind": "page", "resourceId": "agency-home", "version": "1.0.0", "name": "…",
      "category": "…", "tags": [], "description": "…", "preview": {…},
      "compatibility": { "schemaVersion": "1" }, "distribution": { "entitlement": "free" },
      "members": [], "payload": { "schema": "pagebuilder/01", "sections": [ … id-free … ] } }
  ],
  "checksum": "sha256-…"
}
```

- `metadata.createdAt` is `null` — no wall-clock value is baked in, so the same
  resource always exports byte-identically (deterministic).
- `metadata.source` is provenance only; **import always re-sources to `imported`**.
- `checksum` is a sha-256 over the canonical `resources`; import verifies it.
- This is the single-file profile of the full [design-package-v2](design-package-v2.md)
  directory/archive format; it deliberately omits bundled assets, fonts, tokens,
  and dependencies (URL-only assets, no downloader).

## Export flow

Design Library card → **Export** → `GET page-builder/library/export?rid=…` →
downloads `<resourceId>.tncms-design`. Export validates the resource first: an
unsafe asset URL or a host-storage reference (`media_id`, `attachment_id`, storage
keys, …) is refused. Locked (PRO) resources do not expose an Export button.

## Import flow

Design Library toolbar → **Import Design** → pick a `.tncms-design` file →
`POST …/package/validate` (dry run) shows package name, resource count, warnings and
blockers → **Import Design** → `POST …/package/import` persists it → the library
reloads and the resource appears under `source = imported`.

## Validation rules

**Package:** schema version `2`, type `design-package`, non-empty resources, a
checksum that matches the contents.

**Each resource:** kind ∈ {block, section, page} (kits are future); a valid
`imported:<kind>:<resourceId>` RID; no duplicates within the package; a supported
payload schema (`pagebuilder/01`); only installed component types; safe asset URLs
only; no host-storage references. Unverified tree schemas and token usage produce
non-blocking **warnings**; re-importing an existing imported RID warns that it will
be replaced.

## Imported resource behaviour

- Source is forced to `imported`, so RIDs (`imported:<kind>:<id>`) can never collide
  with or overwrite `builtin` (starter/local) or `user` resources.
- Preview runs through the existing Runtime Preview Engine — there is no package
  renderer.
- Imported **pages** work with Full Page Import exactly like any other page.
- The Source filter (`builtin` / `imported` / …) makes imported designs
  discoverable; they appear immediately after import.

## Relationship to existing systems

- Reuses the repository, preview engine, registries, safe-URL policy, and the
  FREE/PRO availability seam unchanged.
- The pre-existing in-memory `PackageResourceProvider` remains a transient
  programmatic registration seam; file imports use the persistent
  `ImportedResourceProvider` instead.
- The editor's TypeScript template-package transport (Phase 26.26) is a separate,
  editor-layer format; this PHP Design Package operates at the DesignResource layer.

## Out of scope

No marketplace, vendor/store, purchase/payment, download server, cloud sync,
signing, or PRO distribution. Those remain reserved seams.
