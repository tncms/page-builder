# Dependency Specification (Phase 26.50A, Rule 6)

> **Status: Foundation over the FROZEN platform.**

A resource **declares** what it depends on. This foundation records dependencies for
**resolution reasoning only** — it installs nothing (installation comes later).

## 1. The `dependencies` block

```json
"dependencies": [
  { "type": "resource", "id": "community:block:cta", "constraint": "^1.0" }
]
```

| Field | Meaning |
|-------|---------|
| `type` | `resource` (implemented) or reserved `plugin` / `extension` / `theme` |
| `id` | a **RID** for a `resource` dependency; a slug for the reserved kinds |
| `constraint` | version constraint (`*` = any), stored verbatim |

## 2. Supported today vs. reserved

- **Builder Resources** (`type: resource`) — fully modelled; the `id` must be a valid
  RID and is validated as such.
- **Plugins / Extensions / Themes** — **reserved** dependency kinds. The format
  accepts them so future resolution needs no manifest change, but this foundation
  adds no plugin/extension/theme resolution.

## 3. Resolution only (Rule 6)

Declaring a dependency does **not** fetch, install or activate anything. There is no
download server and no sync (Rule 18). A future dependency resolver reads these
declarations to compute an install/resolution plan; that resolver is out of scope
here.

## 4. Value object

`ResourceDependency` is immutable:

```php
$dep->type;          // "resource"
$dep->id;            // "community:block:cta"
$dep->constraint;    // "^1.0"
$dep->isResource();  // true
$dep->isKnownType(); // true
```

## 5. Validation

The validator confirms each dependency has a known `type` and a non-empty `id`, and
that a `resource` dependency's `id` is a valid RID.

See also: [resource-manifest.md](resource-manifest.md),
[compatibility-spec.md](compatibility-spec.md).
