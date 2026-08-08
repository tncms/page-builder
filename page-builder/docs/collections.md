# Collections (Phase 26.50D)

A **collection** is a curated grouping for discovery — "Starter Designs", "Landing
Pages", "SaaS Templates", "Business Websites", "Portfolio Designs" (Rule 4).

## 1. Descriptor + additive membership

A `Collection` is a display descriptor only:

| Field | Meaning |
| --- | --- |
| `key` | Stable slug used for filtering. |
| `title` | Human title. |
| `description` | Optional blurb. |
| `kind` | Optional kind scoping (page / section / block / kit). |
| `coverRid` | Optional cover resource. |
| `sortWeight` | Ordering among collections. |

Membership is **additive metadata carried per-resource** by
`CatalogMetadata::$collections`: a resource belongs to a collection when its catalog
overlay lists the collection key. A collection therefore stores no payload and never
becomes another repository (Rule 1).

## 2. Registering collections

Collections are injected (config `page-builder.design_library.catalog.collections`):

```php
'collections' => [
    ['key' => 'starter', 'title' => 'Starter Designs', 'sort_weight' => 0],
    ['key' => 'saas',    'title' => 'SaaS Templates',  'kind' => 'page'],
],
'curation' => [
    'community:page:acme' => ['collections' => ['starter', 'saas']],
],
```

## 3. Querying

```php
$discovery->collections();                 // ordered descriptors
$discovery->collection('starter');         // one descriptor
$discovery->discover(DiscoveryQuery::make()->inCollection('starter'));
```

Collection filtering is AND-combined with every other discovery filter.
