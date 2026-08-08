# Design Library Platform (Phase 26.45)

The Design Library is the **single** platform through which every reusable design
artifact — for FREE, PRO, Marketplace, Community, and Enterprise — is discovered,
previewed, and imported. There is exactly one architecture. Editions differ only
in *which resources are available*, never in how the library works.

## One architecture, many sources

FREE and PRO share, without forking:

| Layer | Class / location | Phase |
|-------|------------------|-------|
| Resource model | `Repository\DesignResource` (immutable) | 26.42 |
| Single source of truth | `Repository\DesignResourceRepository` | 26.42 |
| Runtime preview | `Preview\PreviewEngine` | 26.41 |
| Library UI service | `Library\DesignLibraryService` | 26.43 |
| Library UI | `resources/views/design-library/*` | 26.43 |
| Full-page import | `Import\FullPageImportService` | 26.44 |
| **Availability seam** | `Library\Availability\*` | **26.45** |

There is **no** `ProDesignResource`, `PremiumTemplate`, `ProRepository`,
`ProPreviewEngine`, or `ProLibrary`. Introducing any of these is a design error.

## Resource kinds

Resources are classified by `DesignResource->kind`:

```
block   section   page   kit
```

The UI exposes these as tabs (*All Designs / Blocks / Sections / Pages / Kits*).
Legacy terms (`template`, `pattern`, `preset`) survive only internally where older
subsystems require them for compatibility; they are not the platform vocabulary.

## Sources

`DesignResource->source` names the origin. Active today:

```
builtin   user   imported
```

Reserved as extension points only (declared, **not** activated):

```
pro   marketplace   remote   cloud   enterprise
```

A new source is added by registering a `DesignResourceProviderInterface` with the
repository — nothing else in the platform changes.

## The repository is the only resolver

Consumers (preview, library UI, import, distribution) resolve resources **only**
through `DesignResourceRepositoryInterface`. They never touch providers, package
files, or the filesystem directly. Availability is layered *above* the repository
(see [free-pro-boundary.md](free-pro-boundary.md)) — the repository still returns
every resource that exists, including locked ones, so they can be listed and
previewed.

## Preview and import boundaries

- **Preview** always flows `DesignResource → PreviewEngine`. There is no
  "template preview" or "pro preview" — one engine, one route, for every resource
  including locked ones.
- **Import** always flows through `FullPageImportService`, unchanged. Entitlement
  is enforced *in front of* it (a locked resource is refused before the service is
  reached); the import workflow itself is identical for FREE and PRO.

## What this phase did not do

No marketplace, licensing, billing, subscription, or PRO resource implementation.
This phase only established the shared platform shape and the availability seam so
those can be added later without touching the runtime, serialization, BuilderState,
CommandBus, or the extension API.
