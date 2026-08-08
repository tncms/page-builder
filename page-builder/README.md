# Page Builder

> Foundation for a structured, block-based Page Builder. The admin editor is a heavy client; the frontend stays lightweight by rendering a structured (non-HTML) layout tree. Phase 1 ships the plugin skeleton only — registries, manager, facade, permissions and an admin status dashboard.

**Slug:** `page-builder` · **Version:** `0.1.0-alpha.1`

The Page Builder Community plugin for TN CMS — single-node visual layout editing,
whole-layout publish, translation, preview, theme override, registries, hooks, and a
read-only render runtime.

This repository is a **deterministic projection** of `plugins/page-builder/` from the TN CMS
monorepo, produced by the PB-DIST-1 exporter. It is generated, not hand-authored: see
`release-manifest.json` for the exact source content hash of this build.

## Installation

```bash
composer require thenguyen/page-builder
```

## Scope

Included: single-node editing · whole-layout publish · translation · preview · theme override ·
registries · hooks · render runtime.

Not included: Cloud · Sync · Revision Server · Collaboration · live multi-user · AI services ·
Marketplace · remote storage.

## License

See `LICENSE`.
