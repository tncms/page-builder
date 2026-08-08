# Resource Manifest Specification (Phase 26.50A, Rules 2/3/13)

> **Status: Official interchange format — v1.0.** The manifest is the ONE
> platform-neutral envelope every provider shares (Community, Marketplace, PRO,
> Enterprise, Private). It has **no proprietary or hidden fields** (Rule 13) and is
> usable unchanged in a Portable Package, Offline Bundle or Enterprise Mirror
> (Rule 14).

## 1. Immutability (Rule 3)

A manifest is **immutable after publishing**. A content change is a **new version**
— a new manifest with a new `fingerprint`. `ResourceManifest` is a readonly value
object; there is no in-place mutation.

## 2. Schema

```json
{
  "schema": "1.0",
  "rid": "community:section:hero-modern",
  "fingerprint": "9f2b…(hex content digest)",
  "resource_type": "section",
  "provider": "community",
  "title": "Modern Hero",
  "description": "A modern hero section.",
  "category": "hero",
  "tags": ["hero", "community"],
  "version": "1.0.0",
  "author": {
    "name": "Jane Doe",
    "website": "https://example.com",
    "profile": "jane",
    "verified": false,
    "badges": [],
    "followers": null
  },
  "compatibility": {
    "page_builder": "^1.0",
    "theme": "*",
    "plugins": { "seo": "^2.0" },
    "extensions": {},
    "minimum_builder_version": "1.0.0"
  },
  "license": { "type": "community", "name": "MIT", "spdx": "MIT", "url": null },
  "dependencies": [
    { "type": "resource", "id": "community:block:cta", "constraint": "^1.0" }
  ],
  "assets": [
    { "type": "image", "ref": "assets/hero.jpg", "role": "background" }
  ],
  "preview": { "thumbnail": "preview/thumb.png", "preview_url": null, "width": null, "height": null }
}
```

## 3. Fields

| Field | Meaning |
|-------|---------|
| `schema` | manifest schema version (currently `1.0`) |
| `rid` | Resource Identity `<source>:<kind>:<resourceId>` — its `source` MUST equal `provider`, its `kind` MUST equal `resource_type` |
| `fingerprint` | deterministic hex content digest (payload identity) |
| `resource_type` | `section` \| `block` \| `page` \| `kit` |
| `provider` | owning channel slug (e.g. `community`) |
| `title` / `description` / `category` / `tags` | descriptive metadata |
| `version` | semantic version of this resource |
| `author` | see [Author Contract](#7-author-rule-10) |
| `compatibility` | see [compatibility-spec.md](compatibility-spec.md) |
| `license` | see [License Contract](#5-license-rule-7) |
| `dependencies` | see [dependency-spec.md](dependency-spec.md) |
| `assets` | see [Asset Contract](#6-assets-rule-8) |
| `preview` | static preview metadata only — the live preview is Runtime Preview |

## 4. Parse / serialize

`ManifestParser` (JSON/array → `ResourceManifest`) and `ManifestSerializer`
(`ResourceManifest` → canonical JSON/array) are exact inverses:
`parse(serialize(m)) ≡ m`. Serialization is deterministic (stable byte output) for
offline bundles. Parsing is total and lenient; **validity is decided by the
validator**, not the parser. The parser throws `InvalidManifestException` only when
the input is not a JSON object at all.

## 5. License (Rule 7)

`license.type` is one of `community` (implemented) or the reserved `commercial` /
`subscription` / `enterprise`. **Metadata only** — no pricing, purchase or licensing
engine. Marketplace later *extends* the license block; it never changes it.

## 6. Assets (Rule 8)

Each asset declares `type` (`image`/`font`/`icon`/`media`/`other`) and an opaque
`ref` (relative path or URL). **Declaration only** — nothing is downloaded, fetched
or rehosted.

## 7. Author (Rule 10)

The manifest owns `name`, `website`, `profile`. `verified`, `badges` and `followers`
are **reserved** for a future identity layer — inert in this foundation (no user
accounts exist).

## 8. Neutrality (Rule 13)

No proprietary fields, no hidden metadata, no runtime. Any provider — open or
commercial — can read and write this exact format.

See also: [community-repository.md](community-repository.md),
[manifest-validator.md](manifest-validator.md).
