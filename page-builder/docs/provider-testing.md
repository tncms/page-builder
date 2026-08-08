# Provider Testing Guide (Phase 26.49B, Rule 7)

> **Status: Extension SDK v1.0 over the FROZEN platform.**

A third-party provider is testable with the **same** compliance tests the shipped
providers pass. The Testing SDK gives you two entry points — a PHPUnit base class
and a trait — both thin wrappers over `ProviderComplianceValidator`.

---

## 1. Extend `ProviderTestCase`

Return your provider; inherit the full compliance suite:

```php
use Plugins\PageBuilder\SDK\ProviderTestCase;
use Plugins\PageBuilder\Contracts\DesignResourceProvider;

final class CommunityDesignProviderTest extends ProviderTestCase
{
    protected function createProvider(): DesignResourceProvider
    {
        return new CommunityDesignProvider;
    }
}
```

You inherit:

- `test_provider_passes_full_compliance`
- `test_provider_find_round_trips_all`
- `test_provider_resources_are_searchable`
- `test_provider_registers_with_a_repository`

`ProviderTestCase` extends `PHPUnit\Framework\TestCase` and runs the provider
against **throwaway repositories only** — no Laravel bootstrapping, no database, no
platform mutation. A consuming plugin that autoloads the package can use it
directly.

---

## 2. Use the `ProviderAssertions` trait

For custom test cases, mix in the assertions:

```php
use Plugins\PageBuilder\SDK\ProviderAssertions;

final class MyProviderTest extends TestCase
{
    use ProviderAssertions;

    public function test_it_is_compliant(): void
    {
        $this->assertProviderCompliant(new CommunityDesignProvider);
    }
}
```

Available assertions:

| Assertion | Checks |
|-----------|--------|
| `assertProviderCompliant($p)` | passes every compliance check (returns the report) |
| `assertProviderNotCompliant($p)` | fails compliance (for negative fixtures) |
| `assertProviderFindMatchesAll($p)` | `find()` round-trips `all()` |
| `assertProviderSupportsSearch($p)` | resources are searchable through the repository |
| `assertProviderRegisters($p)` | compliance-gated registration accepts it and resolves its resources |
| `assertProviderRejected($p)` | registration refuses it and leaves the repository untouched |

The trait's assertions delegate to `PHPUnit\Framework\Assert`, so they work in any
TestCase — Laravel or plain PHPUnit.

---

## 3. Autoload note (this repository)

The page-builder plugin exposes a single `src/` PSR-4 root, registered dynamically
at app boot. In-repo tests therefore reference plugin types **at runtime** (inside
test bodies) rather than via a class-level `extends`/`use`, because the plugin
autoloader is not pre-registered by the suite bootstrap. A downstream plugin that
declares the package in its own Composer `autoload` has the loader active at
bootstrap and can extend `ProviderTestCase` directly.

`Tests\Feature\ProviderSdk\ProviderSdkComplianceTest` demonstrates the full SDK —
compliance, gated registration, rejection, search, availability and preview — using
the trait through a runtime object, exactly as a downstream consumer would inherit
it.

See also: [provider-sdk.md](provider-sdk.md),
[provider-compliance.md](provider-compliance.md).
