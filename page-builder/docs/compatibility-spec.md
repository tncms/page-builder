# Compatibility Specification (Phase 26.50A, Rules 5 & 9)

> **Status: Foundation over the FROZEN platform.**

Every resource **declares** what it is compatible with. The platform **never
infers** compatibility (Rule 5). This foundation records the declarations; it does
not resolve or enforce them (installation comes later — Rule 6).

## 1. The `compatibility` block

```json
"compatibility": {
  "page_builder": "^1.0",
  "theme": "*",
  "plugins": { "seo": "^2.0" },
  "extensions": {},
  "minimum_builder_version": "1.0.0"
}
```

| Field | Meaning |
|-------|---------|
| `page_builder` | Builder version constraint the resource targets (`*` = any) |
| `theme` | theme compatibility (`*` = theme-agnostic) |
| `plugins` | map of required plugin → version constraint |
| `extensions` | **reserved** — map of future extension → version constraint |
| `minimum_builder_version` | the minimum Builder version the resource needs (Rule 9) |

Constraints use the familiar `^1.0` / `>=1.2` grammar and are stored **verbatim** —
the foundation does not evaluate them.

## 2. Version ownership (Rule 9)

The manifest owns **version**, **minimum version** and **compatibility**. There is
**no history and no rollback** here — a new version is a new manifest with a new
fingerprint (Rule 3).

## 3. Value object

`CompatibilityMetadata` is an immutable value object:

```php
$compat->pageBuilder;             // "^1.0"
$compat->theme;                   // "*"
$compat->isThemeAgnostic();       // true
$compat->plugins;                 // ["seo" => "^2.0"]
$compat->minimumBuilderVersion;   // "1.0.0"
```

## 4. Validation

The validator confirms `page_builder` and `theme` are declared (empty is rejected;
use `*` for any) and that `minimum_builder_version`, if present, is semantic. It
never checks whether the current environment *satisfies* the constraint — that is a
future resolver's job.

See also: [resource-manifest.md](resource-manifest.md),
[dependency-spec.md](dependency-spec.md).
