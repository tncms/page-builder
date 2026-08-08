# Catalog Metadata Model (Phase 26.50D)

`CatalogMetadata` is the additive, presentation-layer projection of a Design Resource
used by discovery (Rule 2). It combines identity/classification READ from the resource
with a curation OVERLAY, and it never modifies identity or carries payload.

## 1. Fields

| Field | Source | Meaning |
| --- | --- | --- |
| `rid`, `kind`, `source`, `category`, `tags`, `name`, `description`, `version` | **Resource** | Identity & classification (read-only). |
| `compatibility` | **Resource** | Declared compatibility (used by filters, never resolved). |
| `collections` | Overlay | Curated collection keys the resource belongs to. |
| `featured`, `recommended`, `new`, `updated` | Overlay | Curated discovery states (Rule 5). |
| `sortWeight` | Overlay | Manual ordering weight for the `featured` sort. |
| `createdAt`, `updatedAt` | Overlay | Timestamps for the `newest` / `updated` sorts. |
| `display` | Overlay | Display-only metadata (badge, headline, author, …). |

## 2. Identity is immutable (Rule 2)

Catalog metadata **must not** modify the rid, fingerprint, payload, version or manifest
identity. The overlay is sanitised on construction:

```php
$meta = CatalogMetadata::fromResource($resource, $curation);
```

Any overlay key in the forbidden set — `rid`, `fingerprint`, `payload`, `version`,
`manifest`, `source`, `kind`, `category` — is **silently dropped**; identity always
comes from the resource. Catalog metadata carries **no payload**, so discovery never
duplicates resource storage (Rule 1).

## 3. Validation

`CatalogMetadata::validateCuration($overlay)` returns a list of violations (empty = ok):

- rejects any identity-bearing field;
- requires `collections` to be a list of strings;
- requires `sort_weight` to be an integer and `display` to be an array.

## 4. Curation overlay shape

```php
'community:page:acme' => [
    'collections' => ['starter', 'saas'],
    'featured'    => true,
    'recommended' => false,
    'new'         => true,
    'updated'     => false,
    'sort_weight' => 10,
    'created_at'  => 1720000000,
    'updated_at'  => 1720500000,
    'display'     => ['badge' => 'Editor’s pick', 'author' => 'TN CMS'],
],
```

Overlays are injected data (config `page-builder.design_library.catalog.curation`),
never a stored repository.
