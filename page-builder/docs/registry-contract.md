# Registry Contract (Phase 26.49A, Rule 9)

> **Status: FROZEN.** Official type:
> `Plugins\PageBuilder\Contracts\RegistryContract`.
> Backing implementation: `Builder\Registry\BuilderResourceRegistry`. There is
> exactly **ONE** registry.

## Responsibilities

The registry is **PRESENTATION-oriented**. It only:

- **aggregates** Builder Resources contributed by providers (which read the
  authoritative repository),
- **groups** them by source and category,
- **filters** by availability (Phase 26.45 entitlement),
- **searches** across every contributed source (unified search),
- produces the **palette metadata** the editor boots from.

## The registry MUST NOT

- own persistence (it never writes),
- own the Builder runtime,
- be duplicated (no second registry, Rule 17).

## Surface

```php
interface RegistryContract
{
    public function registerProvider(BuilderResourceProvider $provider): void; // idempotent by key
    public function refresh(): void;                 // drop the computed palette cache
    public function providers(): array;
    public function palette(): array;                // kind => PaletteGroup[]
    public function toEditorArray(): array;          // { section: [...], block: [...] }
    public function search(string $term): array;
}
```

## Caching & rebuild policy

The computed palette is cached and only rebuilt when the contributed resource set
changes (signature = sum of provider revisions + provider count). It is **never**
rebuilt on every Builder load. Metadata edits deliberately do not force a rebuild
(see Phase 26.49 / [design-resource-management.md](design-resource-management.md)).

## Insertion

The registry does **not** insert. The editor inserts a resource's id-free payload
through the existing insert-pattern / insert-component commands — there is no second
insertion engine.

## Kind isolation

Section resources register only as sections, block resources only as blocks; `kit`
is reserved and never enters the palette.
