# Page Builder — Extension API (Developer Guide)

**Page Builder Developer SDK v1.0.0** — the frozen public contract for extending Page Builder FREE
from a Laravel plugin or theme (PHP) and from the editor bundle (TypeScript). Every API in this guide
is classified **Public** in the
[Contract Inventory](./audits/phase-26.40-extension-api-freeze-audit.md) and protected by the
[Versioning & Deprecation Policy](./page-builder-versioning-and-deprecation-policy.md). Registry
behavior is specified in the [Registry Specification](./page-builder-registry-specification.md).

> **Golden rule:** extend Page Builder **only** through the Public contracts below. Never call the
> runtime state machine directly — `CommandBus`, `BuilderState`, serialization, and undo/redo are
> **Frozen-Internal**. All layout mutation flows through the single command pipeline; your extensions
> plug in at component, registry, provider, and hook seams.

---

## 1. Architecture

```
   PHP (server)                                   TypeScript (editor bundle)
 ┌───────────────────────────┐                  ┌───────────────────────────────┐
 │ ComponentDefinition       │  editorMetadata  │ Inspector (field editors)     │
 │ Block/SectionRegistry     │ ───────────────▶ │ Insert launcher (categories)  │
 │ ComponentRuntime + Hooks  │                  │ Pattern/Template providers    │
 │ TokenRegistry / Presets   │                  │ Asset media-picker seam       │
 │ PatternRegistry / Preview │                  └───────────────┬───────────────┘
 │ PageBuilder facade        │                                  │ intent
 └───────────────┬───────────┘                  ┌───────────────▼───────────────┐
                 │ render / resolve             │ editor store → executeCommand │
                 ▼                              │  = the ONE command boundary   │
        rendered HTML                           └───────────────┬───────────────┘
                                                                ▼
                                                CommandBus · BuilderState  (FROZEN)
```

- **PHP** owns the component model, the runtime, and server-side registries. This is where you add
  new components, runtime hooks, design tokens, column presets, and pattern preview providers.
- **TypeScript** owns the editing surfaces. This is where you add custom field editors, insert
  categories, media pickers, and swap pattern/template storage providers.
- The two meet at `PageBuilder::editorMetadata()` (server → editor boot payload).

## 2. Extension map

| I want to… | Surface | API | Section |
|------------|---------|-----|---------|
| Add a block/section component | PHP | `AbstractBlockDefinition` / `AbstractSectionDefinition` + `PageBuilder::registerBlock/registerSection` | §3.1 |
| Run logic during resolve/render | PHP | `RuntimeHookManager::on()` | §3.2 |
| Add a design token | PHP | `TokenRegistry::register()` | §3.3 |
| Add column-layout presets | PHP | `ColumnLayoutPresetProvider` + `ColumnLayoutPresetRegistry::register()` | §3.4 |
| Add a pattern preview strategy | PHP | `PreviewProvider` + `PreviewProviderRegistry::register()` | §3.5 |
| Add a custom Inspector field editor | TS | `registerFieldEditor()` | §4.1 |
| Add an Insert category | TS | `InsertProviderRegistry.register()` | §4.2 |
| Provide a media picker | TS | `registerMediaPicker()` | §4.3 |
| Swap pattern/template storage | TS | implement `PatternProvider` / `TemplateProvider` | §4.4 |
| Ship template/starter packs | TS | `registerBuiltinPacks()` / `registerStarterPacks()` | §4.5 |
| Import/export template packages | TS | `exportTemplatePackage()` / `importTemplatePackage()` | §4.6 |

---

## 3. PHP extension points

### 3.1 Add a component (block or section)

Extend the supplied abstract; only `type()` and `label()` are required.

```php
use Plugins\PageBuilder\Definitions\AbstractBlockDefinition;
use Plugins\PageBuilder\Schema\Schema;   // schema builder

final class CalloutBlockDefinition extends AbstractBlockDefinition
{
    public function type(): string  { return 'callout'; }
    public function label(): string { return 'Callout'; }
    public function category(): string { return 'content'; }   // optional (default 'basic')

    public function defaults(): array
    {
        return ['title' => 'Heads up', 'tone' => 'info'];
    }

    public function schema(): array
    {
        return Schema::make()
            ->text('title', 'Title')
            ->select('tone', 'Tone', ['info' => 'Info', 'warn' => 'Warning'])
            ->toArray();
    }
}
```

Register it in your plugin/theme service provider `boot()`:

```php
use Plugins\PageBuilder\Facades\PageBuilder;

PageBuilder::registerBlock('callout', CalloutBlockDefinition::class);
// Sections: PageBuilder::registerSection('hero', HeroSectionDefinition::class);
```

`registerBlock`/`registerSection` accept a class-string, a `ComponentDefinition` instance, or a plain
array. Registration is **last-write-wins** — a later plugin can intentionally override a core type.

### 3.2 Runtime hooks

Run logic at the four lifecycle points (`beforeResolve`, `afterResolve`, `beforeRender`,
`afterRender`). The callback receives the live `ComponentInstance`.

```php
use Plugins\PageBuilder\Runtime\RuntimeHookManager;
use Plugins\PageBuilder\Contracts\ComponentInstance;

app(RuntimeHookManager::class)->on('beforeRender', function (ComponentInstance $instance): void {
    if ($instance->type() === 'callout' && ! $instance->isVisible()) {
        // inspect / adjust before the Blade view renders
    }
});
```

Hooks are additive and fire in registration order. Use them instead of subclassing `ComponentRuntime`
(which is Internal).

### 3.3 Design tokens

```php
use Plugins\PageBuilder\Design\DesignToken;

// group, name, value, ?label
tokenRegistry()->register(new DesignToken('color', 'brand', '#4f46e5', 'Brand'));
```

Tokens are keyed by `group.name` and appear in the editor palette. Re-registering a key **overrides**
it (themes intentionally reskin core tokens).

### 3.4 Column-layout presets

Implement the provider and register it; `priority()` breaks ties (higher wins).

```php
use Plugins\PageBuilder\Presets\Contracts\ColumnLayoutPresetProvider;
use Plugins\PageBuilder\Presets\ColumnLayoutPresetRegistry;

final class MarketingPresets implements ColumnLayoutPresetProvider
{
    public function presets(): array
    {
        return [[
            'id'       => 'sidebar-8-4',
            'label'    => 'Content + Sidebar',
            'widths'   => [8, 4],           // 12-column grid
            'category' => 'marketing',
            'order'    => 10,
        ]];
    }

    public function priority(): int { return 10; } // core ships at 0
}

app(ColumnLayoutPresetRegistry::class)->register(new MarketingPresets());
```

### 3.5 Pattern preview providers

```php
use Plugins\PageBuilder\Patterns\Preview\PreviewProvider;
use Plugins\PageBuilder\Patterns\Preview\PreviewProviderRegistry;
use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Patterns\Preview\PatternPreview;

final class SvgPreviewProvider implements PreviewProvider
{
    public function type(): string { return 'svg'; }
    public function supports(string $type): bool { return $type === 'svg'; }
    public function resolve(Pattern $pattern): PatternPreview { /* build preview */ }
}

app(PreviewProviderRegistry::class)->register(new SvgPreviewProvider());
```

The provider is selected by a pattern manifest's `preview.type`; unknown types fall back to the
default image provider.

---

## 4. TypeScript extension points

Import from the editor bundle's public modules (paths relative to `resources/editor`).

### 4.1 Custom Inspector field editor

```ts
import { registerFieldEditor } from './components/inspector/fieldEditorRegistry'
import ColorPickerField from './my/ColorPickerField.vue'

// `needsField: true` passes the full SchemaField (options, min/max, nested field) to your component.
registerFieldEditor('color-swatch', { component: ColorPickerField, needsField: true })
```

Registering a `type` that already exists **overrides** it (a DEV-only console warning is emitted). Your
component edits the field value; it must not dispatch commands.

### 4.2 Add an Insert category

```ts
import { insertProviderRegistry, makeInsertProvider } from './insert'

insertProviderRegistry.register(
  makeInsertProvider('icons' as never, () => openMyIconPicker()),
)
```

`register()` **throws `InsertRegistryError`** on a duplicate key (identity-strict). Providers only
**open** an experience — they never insert, resolve, or dispatch a command. The launcher renders
built-in categories in `INSERT_CATEGORY_ORDER`.

### 4.3 Provide a media picker

```ts
import { registerMediaPicker } from './components/inspector/mediaPicker'

registerMediaPicker(async (request) => {
  const chosen = await openMyLibrary(request)     // MediaPickerRequest
  return chosen ? { src: chosen.url, alt: chosen.alt } : null  // MediaPickerResult | null
})
```

The picker returns a plain URL result; the value shape and public renderer are unchanged. Passing
`null` clears the seam (falls back to manual URL entry).

### 4.4 Swap pattern / template storage

Implement the provider interface and point the seam at your instance to back patterns/templates with a
database or API instead of in-memory storage.

```ts
import type { PatternProvider } from './patterns/provider'
import type { TemplateProvider } from './templates/contracts/TemplateProvider'

class ApiTemplateProvider implements TemplateProvider {
  all() { /* … */ return [] }
  get(id: string) { /* … */ return undefined }
  register(t) { /* … */ return t }         // throw on duplicate id to match LocalTemplateProvider
  update(id, patch) { /* … */ return undefined }
  remove(id: string) { /* … */ return false }
}
```

Pickers, runtime, and undo/redo are unchanged — they consume the interface, not the implementation.

### 4.5 Ship template / starter packs

```ts
import { registerBuiltinPacks } from './templates/builtin'
import { registerStarterPacks } from './templates/starterPacks'

// Idempotent by pack id; validation-first (throws BuiltinPackError / StarterPackError on invalid).
registerBuiltinPacks({ freeze: true })
registerStarterPacks({ freeze: true })
```

A built-in pack bundles `TemplateDefinition`s; a starter pack references template ids (it never owns
them). Both register once at boot and can be frozen afterward.

### 4.6 Import / export template packages

```ts
import { exportTemplatePackage, serializeTemplatePackage } from './templates/package'
import { importTemplatePackage, parseTemplatePackage } from './templates/package'

const pkg = exportTemplatePackage(defs, { id: 'my-pack', name: 'My Pack' })
const json = serializeTemplatePackage(pkg)                 // portable artifact

const result = importTemplatePackage(parseTemplatePackage(json))  // atomic, validation-first
// result: { ok, packageId, templateIds, warnings }
```

Import is **atomic** — a malformed/invalid/incompatible/unsafe/duplicate package throws
`TemplatePackageError` **before** any registration, so there is never partial state.

---

## 5. Events

| Event / hook | Fires | Payload | Tier |
|--------------|-------|---------|------|
| `beforeResolve` / `afterResolve` | around component `resolve()` | `ComponentInstance` | **Public** |
| `beforeRender` / `afterRender` | around component `render()` | `ComponentInstance` | **Public** |
| `cms.content.body` (filter) | rendering a published layout | `(string $body, ?Content)` | Internal |
| `cms.form.schema.{ownerType}` (filter) | building an edit form | `(array $components)` | Internal |

Extend behavior through the four runtime hooks (§3.2). The `cms.*` filters are **core integration
points**, not extension seams — use the registries/facade instead.

## 6. Lifecycle

```
Register (boot) → Validate → Discover → Use → Dispose (optional)
```

- **Register once, at boot** (PHP service-provider `boot()`, TS `main.ts`). Never register during a
  component's render.
- **Validation-first:** packs/packages throw a typed error before anything is stored (atomic).
- **Discovery** returns immutable metadata snapshots; it never resolves trees or dispatches commands.
- **Use** routes through the single command pipeline → one undo entry.
- **Dispose** is optional (`clear()`/`flush()`/`remove()`), mainly for tests/re-wiring.

See the [Registry Specification](./page-builder-registry-specification.md) for exact ordering,
duplicate, and immutability rules per registry.

## 7. Best practices

- **Register once, at boot; make it idempotent.** Guard your own registration so a double-boot is safe.
- **Respect duplicate policy.** Identity-strict registries (Insert, template provider, distribution,
  starter packs) throw on collisions — catch and surface, don't retry blindly. Last-write-wins
  registries (components, tokens, field editors) let you *intentionally* override — do so deliberately.
- **Never mutate a returned definition.** Use `update()` / `duplicate()`; treat reads as immutable.
- **Keep discovery pure.** A provider/library/picker must never call `executeCommand` or touch
  BuilderState — hand off to the existing apply/insert pipeline.
- **Only URLs for assets.** Media fields store plain URLs; run them through `isSafeAssetUrl` — never a
  storage id or `javascript:`/`data:` scheme.
- **Fail safe at boot.** A throwing extension should degrade to "absent," never crash the editor;
  wrap your registration in try/catch as the core does.
- **Use only Public APIs.** Do not reach into `CommandBus`, `BuilderState`, serialization, or
  `ComponentRuntime` internals — they are Frozen-Internal and unversioned.

## 8. Compatibility & versioning

- The Public contracts here are frozen as **Developer SDK v1.0.0**.
- Additive changes (new component, token, category key, optional field) are **MINOR**; breaking a
  frozen contract is **MAJOR** and requires a deprecation cycle. See the
  [Versioning & Deprecation Policy](./page-builder-versioning-and-deprecation-policy.md).
- Reserved keys (`marketplace`, `remote`, `cloud`) and the migration seam are **Experimental** — do
  not depend on them until they are promoted to Public.
