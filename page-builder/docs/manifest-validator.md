# Manifest Validator Guide (Phase 26.50A, Rule 15)

> **Status: Foundation over the FROZEN platform.**

`ManifestValidator` is the single baseline validator for Resource Manifests. It
rejects malformed manifests field-by-field so a repository never serves a broken
resource. A future Marketplace / Enterprise validator **extends** this contract
(`ManifestValidatorContract`) with license/purchase checks; the shared validation
here stays the one baseline every provider passes (Rule 12).

## 1. Usage

```php
use Plugins\PageBuilder\Community\Manifest\ManifestValidator;

$result = (new ManifestValidator)->validate($manifestArrayOrObject);

$result->passed();   // bool
$result->errors();   // array<{field, message}>
$result->fields();   // distinct failed field names
$result->summary();  // single-line reason
```

`validate()` accepts a `ManifestContract`, a `ResourceManifest`, or a raw array
(parsed internally). It never throws on invalid content — it *reports*. Only the
parser throws, and only when the input is not a JSON object.

## 2. Checks

| Field | Rule |
|-------|------|
| `schema` | must equal the supported schema (`1.0`) |
| `rid` | valid `<source>:<kind>:<resourceId>`; `source` == `provider`; `kind` == `resource_type` |
| `fingerprint` | non-empty hex content digest |
| `resource_type` | one of `section` / `block` / `page` / `kit` |
| `provider` | non-empty lowercase slug |
| `title` | required |
| `version` | semantic (`1.0.0`) |
| `compatibility` | `page_builder` & `theme` declared (`*` allowed); `minimum_builder_version` semantic if set — **never inferred** (Rule 5) |
| `dependencies` | each has a known type + id; `resource` deps must be a valid RID |
| `license` | type ∈ {`community`, `commercial`, `subscription`, `enterprise`} |
| `assets` | each has a known type + non-empty `ref` |
| `author` | `name` required |
| `preview` | `width`/`height` non-negative if set |

## 3. What it never does (Rule 18)

- No compatibility **inference** — it only confirms declarations are well-formed.
- No commerce, pricing, purchase or licensing-engine logic.
- No network, download or sync.

See also: [resource-manifest.md](resource-manifest.md),
[compatibility-spec.md](compatibility-spec.md),
[dependency-spec.md](dependency-spec.md).
