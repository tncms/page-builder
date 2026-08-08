# Phase 26.40 — Extension API Freeze: Audit & Public Contract Inventory

**Status: FROZEN (Developer SDK v1.0.0).** This document declares the **public extension surface** of
Page Builder FREE and classifies every candidate contract as **Public**, **Internal**,
**Experimental**, or **Frozen-Internal**. Only **Public** contracts carry the compatibility guarantees
in the [Versioning & Deprecation Policy](../page-builder-versioning-and-deprecation-policy.md).

- **Method:** three parallel read-only extractions (TS templates; TS patterns/insert/assets/fields;
  PHP runtime), every signature copied verbatim from source, then classified.
- **No code changed** — freezing is a documentation + test act. Runtime / CommandBus / BuilderState /
  serialization untouched. Suite green (1351).

Classification tiers:

| Tier | Meaning | Guarantee |
|------|---------|-----------|
| **Public** | Extension authors implement / call / register against it | SemVer-protected (see policy) |
| **Internal** | Wiring / impl detail; may change any time | none |
| **Experimental** | Reserved-but-inert seam (name reserved, no impl) | none until promoted |
| **Frozen-Internal** | Core state machine; not an extension surface, not callable by extensions | not exposed |

---

## 1. PHP runtime — public surface

### 1.1 Component authoring (implement to add a component)
| Contract | File:line | Public methods | Tier |
|----------|-----------|----------------|------|
| `ComponentDefinition` | `src/Contracts/ComponentDefinition.php:17` | type,label,category,icon,defaults,schema,supports,assets,editor,toArray | **Public** |
| `BlockDefinition` | `src/Contracts/BlockDefinition.php:11` | (extends ComponentDefinition, marker) | **Public** |
| `SectionDefinition` | `src/Contracts/SectionDefinition.php:11` | + allowedBlocks | **Public** |
| `AbstractComponentDefinition` | `src/Definitions/AbstractComponentDefinition.php:19` | abstract type()/label(); safe defaults for the rest | **Public** |
| `AbstractBlockDefinition` | `src/Definitions/AbstractBlockDefinition.php:12` | category()='basic' | **Public** |
| `AbstractSectionDefinition` | `src/Definitions/AbstractSectionDefinition.php:13` | category()='layout', allowedBlocks() | **Public** |
| `ComponentInstance` | `src/Contracts/ComponentInstance.php:20` | hydrate/kind/type/…/resolve/render + beforeResolve/afterResolve/beforeRender/afterRender | **Public** (override hooks) |
| `AbstractComponentInstance` | `src/Runtime/AbstractComponentInstance.php:25` | 4 overridable lifecycle hooks | **Public** |

### 1.2 Registries & runtime
| Contract | File:line | Register/Public | Duplicate | Tier |
|----------|-----------|-----------------|-----------|------|
| `AbstractRegistry` | `src/Registries/AbstractRegistry.php:18` | register/get/has/all/types/count/forget/flush | **overwrite** (last wins) | **Public** |
| `BlockRegistry` | `src/Registries/BlockRegistry.php:15` | (extends AbstractRegistry) | overwrite | **Public** |
| `SectionRegistry` | `src/Registries/SectionRegistry.php:14` | (extends AbstractRegistry) | overwrite | **Public** |
| `RuntimeHookManager` | `src/Runtime/RuntimeHookManager.php:18` | on/dispatch/count/flush; POINTS=[beforeResolve,afterResolve,beforeRender,afterRender] | additive | **Public** |
| `ComponentRuntime` | `src/Runtime/ComponentRuntime.php:22` | hooks/setEditorMode/editorMode/boot/render | — | **Internal** (extend via hooks, don't subclass) |

### 1.3 Patterns / presets / design / facade
| Contract | File:line | Public methods | Duplicate | Tier |
|----------|-----------|----------------|-----------|------|
| `PatternRegistry` | `src/Patterns/PatternRegistry.php:17` | discover/register/all/find/has/featured/search/filter/categories/packs… | higher-priority source wins | **Public** |
| `PatternInterface` | `src/Patterns/Contracts/PatternInterface.php:15` | id/manifest/payload | — | **Public** |
| `PreviewProvider` | `src/Patterns/Preview/PreviewProvider.php:15` | type/supports/resolve | — | **Public** |
| `PreviewProviderRegistry` | `src/Patterns/Preview/PreviewProviderRegistry.php:13` | register/forType/has/types | override by type | **Public** |
| `PatternCapabilityRegistry` | `src/Patterns/Dependencies/PatternCapabilityRegistry.php:14` | register/has/all | idempotent | **Public** |
| `PatternPackRegistry` | `src/Patterns/Packs/PatternPackRegistry.php:11` | register/find/has/all | overwrite | **Internal** (discovery-wired) |
| `ColumnLayoutPresetProvider` | `src/Presets/Contracts/ColumnLayoutPresetProvider.php:16` | presets/priority | higher priority wins | **Public** |
| `ColumnLayoutPresetRegistry` | `src/Presets/ColumnLayoutPresetRegistry.php:18` | register/all/categories | merge by priority | **Public** |
| `TokenRegistry` | `src/Design/TokenRegistry.php:15` | register/registerMany/has/get/all/groups/toArray/breakpoints/defaults | override by key | **Public** |
| `PageBuilder` (facade) | `src/Facades/PageBuilder.php:35` | registerSection/registerBlock/sections/blocks/runtime/tokenRegistry/columnLayoutPresets/render… | — | **Public** |

### 1.4 Events
| Event / hook | Where | Payload | Tier |
|--------------|-------|---------|------|
| `beforeResolve` / `afterResolve` / `beforeRender` / `afterRender` | `RuntimeHookManager` | `ComponentInstance` | **Public** |
| `cms.content.body` (filter) | `PageBuilderServiceProvider` | `(string $body, ?Content)` | **Internal** (core integration) |
| `cms.form.schema.{ownerType}` (filter) | `PageBuilderServiceProvider` | `(array $components)` | **Internal** (core integration) |

### 1.5 Frozen-Internal (do NOT extend)
`CommandBus` (`src/State/CommandBus.php:20`), `Command` (`src/State/Commands/Command.php:17`),
`AbstractCommand` (`src/State/Commands/AbstractCommand.php:15`), `BuilderState`, serialization,
undo/redo history. Built-in commands only; no third-party command implementations. Extensions mutate
layout **only** through the component/registry/hook surfaces above.

---

## 2. TS editor — public surface

### 2.1 Patterns
| Contract | File:line | Duplicate | Tier |
|----------|-----------|-----------|------|
| `PatternProvider` | `patterns/provider/PatternProvider.ts:11` | (impl-defined) | **Public** |
| `patternProvider` seam / `LocalPatternProvider` | `patterns/provider/index.ts:10` | register **overwrites** by id | **Public** |
| `PatternLibraryRegistry` | `patterns/library/registry.ts:35` | register overwrites; duplicate() fresh id | **Public** |
| `PatternDefinition` / `PatternTree` / `PATTERN_LIBRARY_CATEGORIES` | `patterns/library/patternDefinition.ts:18/34/54` | — | **Public** |
| `insertPattern` / `serializePattern` / `restorePattern` | `patterns/library/{insert,serialize,restore}.ts` | — | **Public** |

### 2.2 Insert
| Contract | File:line | Duplicate | Tier |
|----------|-----------|-----------|------|
| `InsertProvider` | `insert/InsertProvider.ts:9` | — | **Public** |
| `InsertProviderRegistry` / `InsertRegistryError` | `insert/InsertProviderRegistry.ts:17/10` | register **throws** on dup key | **Public** |
| `INSERT_CATEGORY` (+`_ORDER`, `isKnownInsertCategory`) | `insert/insertCategory.ts:7` | — | **Public** (all 4 active) |
| `makeInsertProvider` / `registerBuiltinInsertProviders` / `defaultInsertOpenHandlers` / `InsertOpenHandlers` | `insert/builtinInsertProviders.ts` | — | **Public** |
| `registerBuilderInsertProviders` / `useInsertPanelStore` / `useInsertSurfacesStore` | `insert/{editorInsertWiring,insertPanelStore,insertSurfaces}.ts` | — | **Public** |

### 2.3 Assets & field editors
| Contract | File:line | Tier |
|----------|-----------|------|
| `AssetReference` / `toMediaResult` / `isAssetReference` | `assets/contracts/AssetReference.ts` | **Public** |
| `AssetPreview` / `resolveAssetPreview` / `isSafeAssetUrl` | `assets/runtime/resolveAssetPreview.ts` | **Public** |
| `registerMediaPicker` / `MediaPicker` / `MediaPickerRequest` / `MediaPickerResult` | `components/inspector/mediaPicker.ts` | **Public** (host seam) |
| `searchMediaLibrary` / `MediaLibraryItem` / `Query` / `Options` | `assets/mediaLibraryClient.ts` | **Public** |
| `registerFieldEditor` / `FieldEditorDescriptor` | `components/inspector/fieldEditorRegistry.ts:33/15` | **Public** (dup **overwrites**, DEV-warns) |
| `SchemaField` / `ComponentSchema` / `SchemaEntry` | `components/inspector/schema.ts` | **Public** |

### 2.4 Templates
| Contract | File:line | Duplicate | Tier |
|----------|-----------|-----------|------|
| `TemplateProvider` / `LocalTemplateProvider` | `templates/contracts/TemplateProvider.ts:13` / `provider/LocalTemplateProvider.ts:20` | register **throws** (`duplicate_registration`) | **Public** |
| `TemplateLibraryProvider` / `templateLibrary` / `TemplateLibraryItem` | `templates/library/*` | — | **Public** |
| `TemplateDefinition` / `TemplateLayoutRef` (tree∣patterns) | `templates/contracts/TemplateDefinition.ts:39/32` | — | **Public** |
| `TemplateDefinitionSource` / `TemplateDefinitionProvider` / `TemplateSourceResolver` / `applyTemplateFromSource` | `templates/source/*` | — | **Public** |
| `TemplateDistributionProvider` / `TemplateDistributionRegistry` / `DistributionRegistryError` / `LibraryBackedDistributionProvider` | `templates/distribution/*` | register **throws** on dup source | **Public** |
| `BuiltinTemplatePackRegistry` / `registerBuiltinPacks` | `templates/builtin/*` | idempotent skip by id; frozen after boot | **Public** |
| `TemplateStarterPackRegistry` / `registerStarterPacks` | `templates/starterPacks/*` | **throws** on dup id; frozen after boot | **Public** |
| `TemplatePackage` I/O (`export/import/parse/serialize`) | `templates/package/*` | atomic import | **Public** |
| `PackageCompatibility` / `checkPackageCompatibility` | `templates/{package,compatibility}/*` | — | **Public** |
| Errors: `TemplateRuntimeError`, `BuiltinPackError`, `TemplatePackageError`, `StarterPackError`, `DistributionRegistryError` | (respective files) | — | **Public** |

---

## 3. Experimental (reserved-but-inert) — NO guarantee

| Reserved key / seam | Where | Note |
|---------------------|-------|------|
| `marketplace`, `remote`, `cloud` | `TEMPLATE_SOURCE`, `DISTRIBUTION_SOURCE`, `STARTER_PACK_SOURCE` | Namespaces reserved; no provider implementation. Activating one is a MINOR (additive) change. |
| `TemplateMigrationProvider` | `templates/compatibility` | Migration seam declared; no migrations implemented. |

These are documented so future PRO/marketplace work needs no redesign — but they are **not** part of
the frozen guarantee until promoted to Public.

---

## 4. Freeze declaration

The **Public** contracts above constitute **Page Builder Developer SDK v1.0.0**. From this point:
- Breaking any Public contract requires a **MAJOR** release and a deprecation cycle (see policy).
- Additive growth (new keys, providers, optional fields) is **MINOR**.
- Internal / Experimental / Frozen-Internal surfaces are excluded from the guarantee.

Determinism, duplicate handling, and immutability per registry are specified in the
[Registry Specification](../page-builder-registry-specification.md) and locked by the freeze test
`resources/editor/__tests__/ExtensionApiFreeze.test.ts` (6 tripwire assertions) plus the pre-existing
per-registry suites.
