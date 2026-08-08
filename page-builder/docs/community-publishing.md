# Community Publishing (Phase 26.50B)

> **Status: Canonical publishing workflow over the FROZEN platform.** This phase
> standardizes how a Design Resource moves from a local draft to a published
> Community Resource. It introduces **no synchronization, no download/upload API, no
> ratings, no reviews, no accounts, no marketplace, no commerce, no license
> activation, no package installation and no dependency resolution** (Rule 18).
> **Publishing is a workflow; synchronization is a transport — never mixed.**

## 1. Where publishing sits

```
Working Copy → Capture Pipeline → Community Repository → Publishing Service
  → Published Resource → Community Provider → Repository → Registry → Library
```

Synchronization begins only **after** this phase.

## 2. The workflow at a glance

```php
$svc = app(CommunityPublishingService::class); // or new CommunityPublishingService($store)

$svc->draft($manifestArray, $payload);   // → Draft   (local only)
$svc->validate($rid);                     // → Validated
$svc->package($rid);                      // → Packaged (package metadata generated)
$svc->publish($rid);                      // → Published (history + manifest lock)
$svc->deprecate($rid);                    // → Deprecated (searchable + warning)
$svc->archive($rid);                      // → Archived  (hidden, restorable)
$svc->restore($rid);                      // → previous status
```

Each call returns a `PublishingResult`. On failure it carries the failing `step`
(`manifest` / `payload` / `dependencies` / `compatibility` / `version`) and the
field `errors`; the status does not advance. Illegal lifecycle transitions and
editing a locked manifest throw `PublishingException`.

## 3. Components

| Component | Responsibility |
|-----------|----------------|
| `ResourceStatus` | the strongly-typed lifecycle enum ([resource-lifecycle.md](resource-lifecycle.md)) |
| `ResourceLifecycle` | the legal-transition state machine (pure logic) |
| `PublishingPipeline` | the deterministic validate → package pipeline ([publishing-workflow.md](publishing-workflow.md)) |
| `CommunityPublishingService` | orchestrates lifecycle + persistence + history |
| `PackageMetadata` | immutable per-publish metadata ([publishing-history.md](publishing-history.md)) |
| `PublishingHistory` | append-only publish log |

## 4. Guarantees

- **Deterministic pipeline** — same input, same steps, same result (Rule 3).
- **Nothing auto-corrected** — validation fails loudly (Rule 5).
- **Immutable package metadata & history** — no rollback, no branching (Rules 6, 11).
- **Manifest immutable after publish** — edit via Working Copy → Capture → new
  publish at a greater version (Rule 14).
- **Provider exposes only published/deprecated** — drafts stay local (Rule 13).
- **Runtime Preview reused** — no new renderer (Rule 12).

## 5. Reuse by commercial providers (Rule 15)

Marketplace, Enterprise and PRO reuse this **identical** workflow, lifecycle, pipeline
and history. Only authorization differs — the workflow itself never forks.

See also: [resource-lifecycle.md](resource-lifecycle.md),
[publishing-workflow.md](publishing-workflow.md),
[publishing-history.md](publishing-history.md),
[deprecation-policy.md](deprecation-policy.md),
[community-repository.md](community-repository.md).
