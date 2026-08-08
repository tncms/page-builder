# Design Resource Platform — Architecture (Phase 26.49A)

> **Status: FROZEN (architecture lock).** After this phase, functionality is added
> by **extending** these boundaries, never by modifying them. See
> [`../docs/design-resource-contract.md`](../docs/design-resource-contract.md).

## Official architecture diagram (Rule 18)

```
                        ┌──────────────────────────────────────────┐
                        │                 BUILDER                   │
                        │  (BuilderState + CommandBus — frozen)     │
                        └───────────────┬──────────────────────────┘
                                        │ Save as Design (selection)
                                        ▼
                        ┌──────────────────────────────────────────┐
                        │           CAPTURE PIPELINE                │  ← ONLY writer
                        │ normalize→validate→fingerprint→preview→   │
                        │ metadata→persist(UserDesignStore)         │
                        └───────────────┬──────────────────────────┘
                          repository.refresh() + registry.refresh()
                                        ▼
   providers ──────────▶ ┌──────────────────────────────────────────┐
   (Built-in, Starter,   │              REPOSITORY                   │  ← READ-oriented,
    Package, Imported,   │  aggregate · resolve · query · search     │     single source
    My Designs, …)       └───────────────┬──────────────────────────┘     of truth
                                        ▼
                        ┌──────────────────────────────────────────┐
                        │               REGISTRY                    │  ← presentation-only
                        │  group · filter(availability) · search    │     palette
                        └───────────────┬──────────────────────────┘
                                        ▼
                        ┌──────────────────────────────────────────┐
                        │               LIBRARY                     │
                        │  discovery / browsing / My Designs mgmt   │
                        └───────────────┬──────────────────────────┘
                                        │ Open in Builder
                                        ▼
                        ┌──────────────────────────────────────────┐
                        │             WORKING COPY                  │  ← isolated draft;
                        │  deep copy of payload; repo never mutates │     repo read-only
                        └───────────────┬──────────────────────────┘
                                        │ Save as Design
                                        ▼
                                 CAPTURE PIPELINE → new Resource
                                        ⟳  (the cycle closes through capture)

            PREVIEW: exactly ONE Runtime Preview engine + route, keyed by RID,
                     consumed by Repository/Library/Registry alike.
```

## Boundaries (each is documented and frozen)

| Boundary       | Orientation        | Owns                                   | Must NOT |
|----------------|--------------------|----------------------------------------|----------|
| Capture Pipeline | write (the only one) | normalize/validate/fingerprint/persist | be bypassed or duplicated |
| Repository     | read               | aggregate/resolve/query/search          | normalize/validate/capture/mutate payload |
| Registry       | presentation       | group/filter/search/palette             | persist; own runtime |
| Working Copy   | isolation          | detached draft from repo payload        | write back to repo |
| Runtime Preview| render             | one preview per RID                     | be replaced by a 2nd renderer |
| Providers      | source             | how resources materialise               | modify repo/registry/capture/preview/runtime |

## Invariants

- **RID** immutable, unique, never reused. **Fingerprint** deterministic,
  payload-only. **Payload** immutable after capture. **Metadata** the only mutable
  surface.
- Exactly one each of: Capture Pipeline, Repository, Registry, Runtime Preview.
- The lifecycle is a cycle **through capture** — never `Repository → Builder →
  Repository`.

## Contracts (code)

`Plugins\PageBuilder\Contracts\`: `DesignResourceContract`,
`DesignResourceProvider`, `RepositoryContract`, `RegistryContract` — see the
per-contract docs under [`../docs/`](../docs/design-resource-contract.md).

## Architecture lock (Rule 20)

This is the official freeze point. Community Library, Marketplace, PRO Library,
Update Existing, Version History and Portable Package are **out of scope** and must
extend — not redesign — this platform.
